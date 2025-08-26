(function() {
    tinymce.create('tinymce.plugins.atm_button', {
        init : function(editor, url) {
            editor.addButton('atm_generate_image', {
                text: 'Generate AI Image',
                icon: 'image',
                onclick: function() {
                    var promptText = window.prompt("Enter a prompt for the image (you can use shortcodes like [article_title]):");

                    if (promptText) {
                        // Show a loading message
                        editor.insertContent('<p id="atm-generating-image"><em>Generating AI image, please wait...</em></p>');

                        jQuery.ajax({
                            url: atm_ajax.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'generate_inline_image',
                                prompt: promptText,
                                post_id: tinymce.get('content').getParam('post_id'),
                                nonce: atm_ajax.nonce
                            },
                            success: function(response) {
                                // Remove the loading message
                                var loadingP = editor.dom.get('atm-generating-image');
                                if (loadingP) {
                                    editor.dom.remove(loadingP);
                                }

                                if (response.success) {
                                    // Insert the generated image
                                    var imgHtml = '<img src="' + response.data.url + '" alt="' + response.data.alt + '" />';
                                    editor.insertContent(imgHtml);
                                } else {
                                    alert('Error: ' + response.data);
                                }
                            },
                            error: function() {
                                // Remove the loading message
                                var loadingP = editor.dom.get('atm-generating-image');
                                if (loadingP) {
                                    editor.dom.remove(loadingP);
                                }
                                alert('An unknown error occurred.');
                            }
                        });
                    }
                }
            });
        }
    });
    tinymce.PluginManager.add('atm_button', tinymce.plugins.atm_button);
})();