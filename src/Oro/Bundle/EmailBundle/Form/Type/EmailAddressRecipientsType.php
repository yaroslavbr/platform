<?php

namespace Oro\Bundle\EmailBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

use Oro\Bundle\EmailBundle\Form\Model\Email;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;

class EmailAddressRecipientsType extends AbstractType
{
    const NAME = 'oro_email_email_address_recipients';

    /** @var ConfigManager */
    protected $cm;

    /**
     * @param ConfigManager $cm
     */
    public function __construct(ConfigManager $cm)
    {
        $this->cm = $cm;
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if (null === $view->parent) {
            return;
        }

        if (!array_key_exists('data', $view->parent->vars) || !$view->parent->vars['data'] instanceof Email) {
            return;
        }

        $email = $view->parent->vars['data'];
        $configs = [
            'route_parameters' => [
                'entityClass' => $email->getEntityClass(),
                'entityId'    => $email->getEntityId(),
            ]
        ];

        $view->vars['configs'] = array_merge_recursive($configs, $view->vars['configs']);
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults([
            'tooltip'        => false,
            'error_bubbling' => false,
            'empty_data'     => [],
            'configs' => [
                'allowClear'         => true,
                'multiple'           => true,
                'route_name'         => 'oro_email_autocomplete_recipient',
                'minimumInputLength' => $this->cm->get('oro_email.minimum_input_length'),
                'per_page'           => 100,
                'containerCssClass'  => 'taggable-email',
                'tags'               => [],
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return 'genemu_jqueryselect2_hidden';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return static::NAME;
    }
}
