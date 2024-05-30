define(['jquery'], function($) {
    'use strict';

    return function() {
        window.Live2 = window.Live2 || {};
        window.Live2.config = window.Live2.config || {};
        window.Live2.config.embedId = $('[data-live2-embed-id]').data('live2-embed-id');
        window.Live2.config.teamId = $('[data-live2-team-id]').data('live2-team-id');
        window.Live2.config.environment = 'production';

        var script = document.createElement('script');
        script.src = 'https://cdn.live2.ai/assets/sdk/latest/live2ai-embed-sdk.js';
        script.async = true;
        document.body.appendChild(script);
    };
});

