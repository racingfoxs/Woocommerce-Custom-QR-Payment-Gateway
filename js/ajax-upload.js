jQuery(document).ready(function($) {
    
    var lastUploadedImageUrl = '';

    $(document).on('change', '#esewa_payment_screenshot', function() {
        var file_data = $(this).prop('files')[0];
        var form_data = new FormData();

        if (file_data) {
            if (file_data.size > 10 * 1024 * 1024) {
                console.error('File size exceeds the limit (10 MB)');
                $('#uploaded_image').html('<p style="color: red;">File size exceeds the limit (10 MB).</p>');
                return;
            }

            form_data.append('esewa_payment_screenshot', file_data);
            form_data.append('action', 'esewa_upload');
            form_data.append('security', ajax_upload_params.nonce);

            $('#uploaded_image').html('<div class="relative">Uploading... <div class="loading-drip"></div></div>');

            $.ajax({
                url: ajax_upload_params.ajax_url,
                type: 'POST',
                data: form_data,
                contentType: false,
                processData: false,
                cache: false,
                success: function(response) {
                    console.log('AJAX Response:', response);
                    try {
                        response = JSON.parse(response);
                        if (response.error) {
                            console.error('File upload failed:', response.error);
                            $('#uploaded_image').html('<p style="color: red;">File upload failed: ' + response.error + '</p>');
                        } else if (response.url) {
                            lastUploadedImageUrl = response.url;
                            $('#uploaded_image').html('<p style="color: green;">Uploaded Payment Screenshot:</p><img src="' + response.url + '" style="max-width:300px;">');
                            $('<input>').attr({
                                type: 'hidden',
                                id: 'esewa_screenshot_url',
                                name: 'esewa_screenshot_url',
                                value: response.url
                            }).appendTo('#uploaded_image');
                        } else {
                            console.error('Unknown response:', response);
                            $('#uploaded_image').html('<p style="color: red;">Unknown error occurred.</p>');
                        }
                    } catch (error) {
                        console.error('Error parsing JSON response:', error);
                        $('#uploaded_image').html('<p style="color: red;">Error occurred while processing server response.</p>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', xhr.status, error);
                    $('#uploaded_image').html('<p style="color: red;">Error occurred while uploading the file.</p>');
                }
            });
        } else {
            
            if (lastUploadedImageUrl) {
                $('#uploaded_image').html('<p style="color: green;">Uploaded Payment Screenshot:</p><img src="' + lastUploadedImageUrl + '" style="max-width:300px;">');
            } else {
                console.error('No file selected.');
                // $('#uploaded_image').html('<p style="color: red;">No file selected.</p>');
            }
        }
    });
});
