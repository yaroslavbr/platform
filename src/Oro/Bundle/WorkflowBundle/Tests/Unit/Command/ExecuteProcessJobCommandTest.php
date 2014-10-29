<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Command;

use Oro\Bundle\WorkflowBundle\Command\ExecuteProcessJobCommand;
use Oro\Bundle\WorkflowBundle\Entity\ProcessJob;
use Oro\Bundle\WorkflowBundle\Tests\Unit\Command\Stub\TestOutput;

class ExecuteProcessJobCommandTest extends \PHPUnit_Framework_TestCase
{
    const PROCESS_JOB_ENABLED   = 'enabled';
    const PROCESS_JOB_NOT_FOUND = 'not_found';

    /**
     * @var ExecuteProcessJobCommand
     */
    private $command;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $container;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $managerRegistry;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $processHandler;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $input;

    /**
     * @var TestOutput
     */
    private $output;

    protected function setUp()
    {
        $this->container = $this->getMock('Symfony\Component\DependencyInjection\ContainerBuilder');
        $this->managerRegistry = $this->getMock('Doctrine\Common\Persistence\ManagerRegistry');
        $this->processHandler = $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Model\ProcessHandler')
            ->disableOriginalConstructor()
            ->getMock();

        $this->command = new ExecuteProcessJobCommand();
        $this->command->setContainer($this->container);

        $this->input   = $this->getMockForAbstractClass('Symfony\Component\Console\Input\InputInterface');
        $this->output = new TestOutput();
    }

    public function testConfigure()
    {
        $this->command->configure();

        $this->assertNotEmpty($this->command->getDescription());
        $this->assertNotEmpty($this->command->getName());
    }

    /**
     * @param array $ids
     * @param array $expectedOutput
     * @param \Exception[] $exceptions
     * @dataProvider executeProvider
     */
    public function testExecute(array $ids, $expectedOutput, array $exceptions = [])
    {
        $this->expectContainerGetManagerRegistryAndProcessHandler();

        $successful = !count($exceptions);

        $this->input->expects($this->once())
            ->method('getOption')
            ->with('id')
            ->will($this->returnValue($ids));

        $processJobs = $this->populateProcessJobs($ids);

        $index = 0;

        foreach ($processJobs as $processJob) {
            $stub = $successful ? $this->returnSelf() : $this->throwException($exceptions[round($index / 2)]);
            $this->processHandler->expects($this->at($index++))
                ->method('handleJob')
                ->with($processJob)
                ->will($stub);
            $this->processHandler->expects($this->at($index++))
                ->method('finishJob')
                ->with($processJob);
        }

        $this->expectProcessJobRepositoryFindByIds($ids, $processJobs);
        $this->expectProcessJobEntityManagerHandleJobs($successful, $processJobs);

        $this->command->execute($this->input, $this->output);

        $this->assertAttributeEquals(
            $expectedOutput,
            'messages',
            $this->output
        );
    }

    /**
     * @param array $ids
     * @return ProcessJob[]
     */
    protected function populateProcessJobs(array $ids)
    {
        $result = [];
        foreach ($ids as $id) {
            $process = $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Entity\ProcessJob')
                ->disableOriginalConstructor()
                ->getMock();
            $process->expects($this->once())
                ->method('getId')
                ->will($this->returnValue($id));
            $result[] = $process;
        }

        return $result;
    }

    /**
     * @return array
     */
    public function executeProvider()
    {
        return array(
            'single id' => array(
                'ids' => array(1),
                'output' => [
                    'Process 1 successfully finished'
                ],
            ),
            'several ids successful' => array(
                'ids' => array(1, 2, 3),
                'output' => [
                    'Process 1 successfully finished',
                    'Process 2 successfully finished',
                    'Process 3 successfully finished',
                ],
            ),
            'several ids failed' => array(
                'ids' => array(1, 2, 3),
                'output' => [
                    'Process 1 failed: Process 1 exception',
                    'Process 2 failed: Process 2 exception',
                    'Process 3 failed: Process 3 exception',
                ],
                'exceptions' => [
                    new \Exception('Process 1 exception'),
                    new \Exception('Process 2 exception'),
                    new \Exception('Process 3 exception'),
                ],
            ),
        );
    }

    public function testExecuteEmptyIdError()
    {
        $this->expectContainerGetManagerRegistryAndProcessHandler();

        $ids = array(1);
        $this->input->expects($this->once())
            ->method('getOption')
            ->with('id')
            ->will($this->returnValue($ids));

        $this->expectProcessJobRepositoryFindByIds($ids, []);
        $this->processHandler->expects($this->never())
            ->method($this->anything());

        $this->command->execute($this->input, $this->output);

        $this->assertAttributeEquals(
            ['Process jobs with passed identifiers do not exist'],
            'messages',
            $this->output
        );
    }

    /**
     * @param bool $successful
     * @param ProcessJob[] $processJobs
     */
    protected function expectProcessJobEntityManagerHandleJobs($successful, array $processJobs)
    {
        $entityManager = $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();

        $this->managerRegistry->expects($this->once())
            ->method('getManagerForClass')
            ->with('OroWorkflowBundle:ProcessJob')
            ->will($this->returnValue($entityManager));

        $index = 0;
        if ($successful) {
            foreach ($processJobs as $processJob) {
                $entityManager->expects($this->at($index++))
                    ->method('beginTransaction');

                $entityManager->expects($this->at($index++))
                    ->method('remove')
                    ->with($processJob);

                $entityManager->expects($this->at($index++))
                    ->method('flush');

                $entityManager->expects($this->at($index++))
                    ->method('commit');
            }
        } else {
            foreach ($processJobs as $processJob) {
                $entityManager->expects($this->at($index++))
                    ->method('beginTransaction');

                $entityManager->expects($this->at($index++))
                    ->method('rollback');
            }
        }
    }

    /**
     * @param array $processJobIds
     * @param ProcessJob[] $processJobs
     */
    protected function expectProcessJobRepositoryFindByIds(array $processJobIds, array $processJobs)
    {
        $repository = $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Entity\Repository\ProcessJobRepository')
            ->disableOriginalConstructor()
            ->getMock();
        $repository->expects($this->once())
            ->method('findByIds')
            ->with($processJobIds)
            ->will($this->returnValue($processJobs));

        $this->managerRegistry->expects($this->once())
            ->method('getRepository')
            ->with('OroWorkflowBundle:ProcessJob')
            ->will($this->returnValue($repository));
    }

    protected function expectContainerGetManagerRegistryAndProcessHandler()
    {
        $this->container->expects($this->atLeastOnce())
            ->method('get')
            ->will(
                $this->returnValueMap(
                    [
                        ['doctrine', 1, $this->managerRegistry],
                        ['oro_workflow.process.process_handler', 1, $this->processHandler],
                    ]
                )
            );
    }
}
