define(function(require) {
    'use strict';

    const _ = require('underscore');
    const Backbone = require('backbone');

    function BasePlugin(main, manager, options) {
        this.cid = _.uniqueId(this.cidPrefix);
        this.main = main;
        this.manager = manager;
        this.options = options;
        this.initialize(main, options);
    }

    _.extend(BasePlugin.prototype, Backbone.Events, {
        cidPrefix: 'plugin',

        /**
         * Constructor
         *
         * @param main {Object} object this plugin attached to
         * @param options {object=}
         */
        initialize: function(main, options) {},

        eventNamespace: function() {
            return this.main.eventNamespace() + this.ownEventNamespace();
        },

        ownEventNamespace: function() {
            return this.main.eventNamespace.call(this);
        },

        /**
         * Enables plugin
         */
        enable: function() {
            this.enabled = true;
            this.trigger('enabled');
        },

        /**
         * Disables plugin
         */
        disable: function() {
            this.enabled = false;
            this.stopListening();
            this.trigger('disabled');
        },

        dispose: function() {
            if (this.disposed) {
                return;
            }
            this.disposed = true;
            this.trigger('disposed');
            this.off();
            this.stopListening();
            for (const prop in this) {
                if (this.hasOwnProperty(prop)) {
                    delete this[prop];
                }
            }
            this.disposed = true;
            return typeof Object.freeze === 'function' ? Object.freeze(this) : void 0;
        }
    });

    BasePlugin.extend = Backbone.Model.extend;

    return BasePlugin;
});
