// The main JavaScript file for HotSoup's book tracking

jQuery(document).ready(function($) {

    // Handles adding books to users' libraries
    $(document).on('click', '.hs-add-book', function(e) {
        e.preventDefault();
        const button = $(this);
        const bookID = button.data('book-id');

        $.ajax({
            url: hs_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'hs_add_book',
                nonce: hs_ajax.nonce,
                book_id: bookID,
            },
            beforeSend: function() {
                button.text('Adding...').prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    // Update the button to show it's been added successfully
                    button.text('Added').css('background-color', '#28a745').prop('disabled', true);
                } else {
                    button.text(response.data.message || 'Error').css('background-color', '#dc3545');
                }
            },
            error: function() {
                button.text('Request Failed').prop('disabled', false).css('background-color', '#dc3545');
            }
        });
    }); // <-- End of hs-add-book

    // Handles removing books from a user's library
    $('.hs-my-book-list').on('click', '.hs-remove-book', function(e) {
        e.preventDefault();

        if (!confirm('Are you sure that you want to remove this book from your library?')) {
            return;
        }

        const button = $(this);
        const book_id = button.data('book-id');
        const list_item = button.closest('li[data-list-book-id="' + book_id + '"]');

        $.ajax({
            url: hs_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'hs_remove_book',
                nonce: hs_ajax.nonce,
                book_id: book_id,
            },
            success: function(response) {
                if (response.success) {
                    list_item.fadeOut(400, function() {
                        $(this).remove();
                    });
                } else {
                    alert('ERROR: ' + response.data.message);
                }
            },
            error: function() {
                alert('BIG PROBLEMO!');
            }
        });
    }); // <-- End of hs-remove-book

    // Handles actually updating progress
    $('.hs-progress-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const feedbackSpan = form.find('.hs-feedback');
        const progressbar = form.siblings('.hs-progress-bar-container').find('.hs-progress-bar');
        const progresstext = form.siblings('span');
        const booklist_item = form.closest('.hs-my-book');

	const new_page_val = parseInt(form.find('input[name="current_page"]').val());
	const max_pages = parseInt(form.find('input[name="current_page"]').attr('max'));

	const is_currently_completed = booklist_item.hasClass('completed');
	const will_be_incomplete = new_page_val < max_pages;
	const has_review = booklist_item.data('reviewed') === true || booklist_item.data('reviewed') === 'true';

	if (is_currently_completed && will_be_incomplete && has_review)
	{
		if (!confirm('WARNING: YOU ARE ABOUT TO MARK THIS BOOK AS "INCOMPLETE" THIS WILL PERMANENTLY DELETE YOUR REVIEW/RATING FOR THIS BOOK. YOU WILL LOSE THE POINTS YOU EARNED FROM YOUR REVIEW/RATING. THIS CANNOT BE REVERSED BY THE ADMINISTRATORS. ARE YOU SURE YOU WOULD LIKE TO CONTINUE?'))
		{
			// coward
			return;
		}
	}

        $.ajax({
            url: hs_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'hs_update_progress',
                nonce: hs_ajax.nonce,
                book_id: form.find('input[name="book_id"]').val(),
                current_page: form.find('input[name="current_page"]').val(),
            },
            beforeSend: function() {
                feedbackSpan.text('Saving').css('color', '#666');
            },
            success: function(response) {
                if (response.success) {
                    progressbar.css('width', response.data.progress_percent + '%');
                    progresstext.text(response.data.progress_html);

                    if (response.data.completed) {
                        feedbackSpan.text('Book Completed!').css('color', '#D4AF37');
                        progressbar.addClass('golden');
                        progressbar.addClass('completed');
                        booklist_item.addClass('hs-book-achievement');

			// Show review button if possible
			booklist_item.find('.hs-review-section').slideDown();

                        setTimeout(function() {
                            booklist_item.removeClass('hs-book-achievement');
                        }, 1000);
                    } else {
                        feedbackSpan.text('Saved.').css('color', 'green');
                        progressbar.removeClass('golden');
                        booklist_item.removeClass('completed');


			// Handle review deletion (client-side)
			if (response.data.review_deleted)
			{
				booklist_item.data('reviewed', 'false');
				const review_section = booklist_item.find('.hs-review-section');
				review_section.find('.hs-toggle-review-form').text('Review Book');
				review_section.find('.hs-user-rating-display').empty();
				review_section.find('.hs-review-form').slideUp();
				review_section.find('input[name="hs_rating"]').val('');
				review_section.find('textarea[name="hs_review_text"]').val('');
			}

			booklist_item.find('.hs-review-section').slideUp();
                    }
                } else {
                    feedbackSpan.text(response.data.message).css('color', 'red');
                }
                setTimeout(() => feedbackSpan.text(''), 2000);
            },
            error: function() {
                feedbackSpan.text('Yikes!').css('color', 'red');
            }
        });
    }); // <-- End of hs-progress-form

	// Toggle review form
	$('.hs-my-book-list').on('click', '.hs-toggle-review-form', function(e)
	{
		e.preventDefault();
		$(this).siblings('.hs-review-form').slideToggle();
	});


	// Handles review form submission
	$('.hs-my-book-list').on('submit', '.hs-review-form', function(e)
	{
		e.preventDefault();
		const form = $(this);
		const feedback_span = form.find('.hs-review-feedback');
		const list_item = form.closest('.hs-my-book');
		const rating_display = list_item.find('.hs-user-rating-display');
		const toggle_button = list_item.find('.hs-toggle-review-form');

		const rating_val = form.find('input[name="hs_rating"]').val();

		// Client-side validation
		if (rating_val && (parseFloat(rating_val) < 0.0 || parseFloat(rating_val) > 10.0))
		{
			feedback_span.text('Your rating must be between 0.0 and 10.0.').css('color', 'red');
			return;
		}

		$.ajax({
			url: hs_ajax.ajax_url,
			type: 'POST',
			data:
			{
				action: 'hs_submit_review',
				nonce: hs_ajax.nonce,
				book_id: form.find('input[name="book_id"]').val(),
				rating: rating_val,
				review_text: form.find('textarea[name="hs_review_text"]').val()
			},

			beforeSend: function()
			{
				feedback_span.text('Saving...').css('color', '#666');
			},

			success: function(response)
			{
				if (response.success)
				{
					feedback_span.text(response.data.message).css('color', 'green');
					list_item.data('reviewed', 'true');
					toggle_button.text('Edit Review');
					rating_display.html(response.data.new_rating_html);

					setTimeout(() =>
					{
						feedback_span.text('');
						form.slideUp();
					}, 2000);
				}

				else
				{
					feedback_span.text(response.data.message).css('color', 'red');
				}
			},

			error: function()
			{
				feedback_span.text('Oops! Something is broken!').css('color', 'red');
			}
		});
	});


    // --- Report Inaccuracy Modal ---
    const report_modal = $('#hs-report-modal');
    const close_btn = $('#hs-close-report-modal');
    const submit_btn = $('#hs-submit-report-button');

    // Use event delegation for the open button
    $(document).on('click', '#hs-open-report-modal', function() {
        report_modal.show();
    });

    // Close button
    if (close_btn.length) {
        close_btn.on('click', function() {
            report_modal.hide();
        });
    }

    // Click outside to close
    $(window).on('click', function(e) {
        if ($(e.target).is(report_modal)) {
            report_modal.hide();
        }
    });

    // Submit report
    if (submit_btn.length) {
        submit_btn.on('click', function() {
            const button = $(this);
            const feedback_div = $('#hs-report-feedback');

            $.ajax({
                url: hs_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'hs_submit_report',
                    nonce: hs_ajax.nonce,
                    book_id: button.data('book-id'),
                    report_text: $('#hs-report-textarea').val()
                },
                beforeSend: function() {
                    button.text('Submitting report...').prop('disabled', true);
                    feedback_div.text('');
                },
                success: function(response) {
                    if (response.success) {
                        feedback_div.text(response.data.message).css('color', 'green');
                        setTimeout(() => {
                            report_modal.hide();
                            button.text('Submit Report').prop('disabled', false);
                            $('#hs-report-textarea').val('');
                            feedback_div.text('');
                        }, 2000);
                    } else {
                        feedback_div.text(response.data.message).css('color', 'red');
                        button.text('Submit Report').prop('disabled', false);
                    }
                },
                error: function() {
                    feedback_div.text('Oops! Error!').css('color', 'red');
                    button.text('Submit Report').prop('disabled', false);
                }
            });
        });
    } // <-- End of submit_btn.length

// --- NEW: User Moderation ---

    // Block User
    $(document).on('click', '.hs-block-user', function(e) {
        e.preventDefault();
        const button = $(this);
        const userId = button.data('user-id');
        const feedbackSpan = button.siblings('.hs-moderation-feedback');

        $.ajax({
            url: hs_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'hs_block_user',
                nonce: hs_ajax.nonce,
                target_user_id: userId,
            },
            beforeSend: function() {
                button.text('Blocking...').prop('disabled', true);
                feedbackSpan.text('');
            },
            success: function(response) {
                if (response.success) {
                    feedbackSpan.text(response.data.message).css('color', 'green');
                    button.text('Unblock User').removeClass('hs-block-user').addClass('hs-unblock-user');
                } else {
                    feedbackSpan.text(response.data.message).css('color', 'red');
                }
                button.prop('disabled', false);
            },
            error: function() {
                feedbackSpan.text('Request Failed.').css('color', 'red');
                button.text('Block User').prop('disabled', false);
            }
        });
    });

    // Unblock User
    $(document).on('click', '.hs-unblock-user', function(e) {
        e.preventDefault();
        const button = $(this);
        const userId = button.data('user-id');
        const feedbackSpan = button.siblings('.hs-moderation-feedback');

        $.ajax({
            url: hs_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'hs_unblock_user',
                nonce: hs_ajax.nonce,
                target_user_id: userId,
            },
            beforeSend: function() {
                button.text('Unblocking...').prop('disabled', true);
                feedbackSpan.text('');
            },
            success: function(response) {
                if (response.success) {
                    feedbackSpan.text(response.data.message).css('color', 'green');
                    button.text('Block User').removeClass('hs-unblock-user').addClass('hs-block-user');
                } else {
                    feedbackSpan.text(response.data.message).css('color', 'red');
                }
                button.prop('disabled', false);
            },
            error: function() {
                feedbackSpan.text('Request Failed.').css('color', 'red');
                button.text('Unblock User').prop('disabled', false);
            }
        });
    });

    // --- Report User Modal ---
    const reportUserModal = $('#hs-report-user-modal');
    const closeUserReportBtn = $('#hs-close-report-user-modal');

    // Open report user modal
    $(document).on('click', '.hs-report-user-modal-open', function() {
        reportUserModal.show();
    });

    // Close button
    if (closeUserReportBtn.length) {
        closeUserReportBtn.on('click', function() {
            reportUserModal.hide();
        });
    }
    
    // Click outside to close
    $(window).on('click', function(e) {
        if ($(e.target).is(reportUserModal)) {
            reportUserModal.hide();
        }
    });

    // Submit User Report
    $(document).on('submit', '#hs-report-user-form', function(e) {
        e.preventDefault();
        const button = $('#hs-submit-user-report-button');
        const feedback_div = $('#hs-report-user-feedback');
        const target_user_id = $('#hs-report-user-id').val();
        const reason = $('#hs-report-user-textarea').val();

        if (reason.trim() === '') {
            feedback_div.text('Please provide a reason.').css('color', 'red');
            return;
        }

        $.ajax({
            url: hs_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'hs_report_user',
                nonce: hs_ajax.nonce,
                target_user_id: target_user_id,
                reason: reason
            },
            beforeSend: function() {
                button.text('Submitting...').prop('disabled', true);
                feedback_div.text('');
            },
            success: function(response) {
                if (response.success) {
                    feedback_div.text(response.data.message).css('color', 'green');
                    setTimeout(() => {
                        reportUserModal.hide();
                        button.text('Submit Report').prop('disabled', false);
                        $('#hs-report-user-textarea').val('');
                        feedback_div.text('');
                    }, 2000);
                } else {
                    feedback_div.text(response.data.message).css('color', 'red');
                    button.text('Submit Report').prop('disabled', false);
                }
            },
            error: function() {
                feedback_div.text('Oops! An error occurred!').css('color', 'red');
                button.text('Submit Report').prop('disabled', false);
            }
        });
    });

    // === CITATION FUNCTIONALITY ===

    // Open citation modal
    $(document).on('click', '.hs-citation-button', function(e) {
        e.preventDefault();
        const button = $(this);
        const bookId = button.data('book-id');
        const bookTitle = button.data('book-title');
        const bookAuthor = button.data('book-author');

        // Set the book ID and display book info
        $('#hs-citation-book-id').val(bookId);
        $('.hs-citation-book-info').html('<strong>Creating citation for:</strong> "' + bookTitle + '" by ' + bookAuthor);

        // Clear previous form data
        $('#hs-citation-form')[0].reset();
        $('#hs-citation-book-id').val(bookId); // Keep book ID after reset
        $('#hs-citation-preview').html('');
        $('#hs-citation-copy-btn').hide();
        $('.hs-citation-feedback').text('');

        // Load saved citations for this book
        loadSavedCitations(bookId);

        // Show the modal
        $('#hs-citation-modal').fadeIn();
    });

    // Close citation modal
    $('.hs-modal-close, #hs-citation-modal').on('click', function(e) {
        if (e.target === this) {
            $('#hs-citation-modal').fadeOut();
        }
    });

    // Preview citation
    $('#hs-citation-preview-btn').on('click', function(e) {
        e.preventDefault();
        const button = $(this);
        const format = $('#hs-citation-format').val();
        const bookId = $('#hs-citation-book-id').val();

        if (!format) {
            alert('Please select a citation format first.');
            return;
        }

        const formData = {
            action: 'hs_regenerate_citation',
            nonce: hs_ajax.nonce,
            book_id: bookId,
            format: format,
            pages: $('#hs-citation-pages').val(),
            publisher: $('#hs-citation-publisher').val(),
            city: $('#hs-citation-city').val(),
            edition: $('#hs-citation-edition').val(),
            translator: $('#hs-citation-translator').val(),
            editor: $('#hs-citation-editor').val(),
            url: $('#hs-citation-url').val(),
            access_date: $('#hs-citation-access-date').val()
        };

        $.ajax({
            url: hs_ajax.ajax_url,
            type: 'POST',
            data: formData,
            beforeSend: function() {
                button.text('Generating...').prop('disabled', true);
                $('#hs-citation-preview').html('<p class="loading">Generating citation...</p>');
            },
            success: function(response) {
                if (response.success) {
                    $('#hs-citation-preview').html('<div class="citation-text">' + response.data.citation_text + '</div>');
                    $('#hs-citation-copy-btn').show();
                } else {
                    $('#hs-citation-preview').html('<p class="error">Error: ' + response.data.message + '</p>');
                }
            },
            error: function() {
                $('#hs-citation-preview').html('<p class="error">Failed to generate citation.</p>');
            },
            complete: function() {
                button.text('Preview Citation').prop('disabled', false);
            }
        });
    });

    // Save citation
    $('#hs-citation-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const feedback = $('.hs-citation-feedback');
        const format = $('#hs-citation-format').val();
        const bookId = $('#hs-citation-book-id').val();

        if (!format) {
            alert('Please select a citation format.');
            return;
        }

        const formData = {
            action: 'hs_create_citation',
            nonce: hs_ajax.nonce,
            book_id: bookId,
            format: format,
            pages: $('#hs-citation-pages').val(),
            publisher: $('#hs-citation-publisher').val(),
            city: $('#hs-citation-city').val(),
            edition: $('#hs-citation-edition').val(),
            translator: $('#hs-citation-translator').val(),
            editor: $('#hs-citation-editor').val(),
            url: $('#hs-citation-url').val(),
            access_date: $('#hs-citation-access-date').val()
        };

        $.ajax({
            url: hs_ajax.ajax_url,
            type: 'POST',
            data: formData,
            beforeSend: function() {
                feedback.text('Saving citation...').css('color', '#333');
            },
            success: function(response) {
                if (response.success) {
                    feedback.text('Citation saved successfully!').css('color', 'green');

                    // Update preview with the saved citation
                    $('#hs-citation-preview').html('<div class="citation-text">' + response.data.citation_text + '</div>');
                    $('#hs-citation-copy-btn').show();

                    // Reload saved citations
                    loadSavedCitations(bookId);

                    // Clear feedback after 3 seconds
                    setTimeout(function() {
                        feedback.text('');
                    }, 3000);
                } else {
                    feedback.text('Error: ' + response.data.message).css('color', 'red');
                }
            },
            error: function() {
                feedback.text('Failed to save citation.').css('color', 'red');
            }
        });
    });

    // Copy citation to clipboard
    $('#hs-citation-copy-btn').on('click', function(e) {
        e.preventDefault();
        const citationText = $('#hs-citation-preview .citation-text').text();

        // Create a temporary textarea to copy from
        const tempTextarea = $('<textarea>');
        tempTextarea.val(citationText);
        $('body').append(tempTextarea);
        tempTextarea.select();

        try {
            document.execCommand('copy');
            $(this).text('Copied!').css('background-color', '#28a745');
            setTimeout(() => {
                $(this).text('Copy to Clipboard').css('background-color', '');
            }, 2000);
        } catch (err) {
            alert('Failed to copy citation. Please copy it manually.');
        }

        tempTextarea.remove();
    });

    // Load saved citations for a book
    function loadSavedCitations(bookId) {
        $.ajax({
            url: hs_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'hs_get_citations',
                nonce: hs_ajax.nonce,
                book_id: bookId
            },
            success: function(response) {
                if (response.success && response.data.citations && response.data.citations.length > 0) {
                    let html = '<ul class="hs-citations-list">';
                    response.data.citations.forEach(function(citation) {
                        html += '<li class="hs-citation-item">';
                        html += '<div class="hs-citation-header">';
                        html += '<strong>' + citation.citation_format.toUpperCase() + '</strong>';
                        html += '<span class="hs-citation-date">' + citation.date_created + '</span>';
                        html += '</div>';
                        html += '<div class="hs-citation-content">' + citation.citation_text + '</div>';
                        html += '<div class="hs-citation-item-actions">';
                        html += '<button class="hs-button hs-button-small hs-copy-saved-citation" data-citation-text="' + $('<div>').text(citation.citation_text).html() + '">Copy</button>';
                        html += '<button class="hs-button hs-button-small hs-button-danger hs-delete-citation" data-citation-id="' + citation.id + '">Delete</button>';
                        html += '</div>';
                        html += '</li>';
                    });
                    html += '</ul>';
                    $('#hs-saved-citations-list').html(html);
                } else {
                    $('#hs-saved-citations-list').html('<p class="hs-no-citations">No saved citations yet. Create one above!</p>');
                }
            },
            error: function() {
                $('#hs-saved-citations-list').html('<p class="error">Failed to load citations.</p>');
            }
        });
    }

    // Copy saved citation
    $(document).on('click', '.hs-copy-saved-citation', function(e) {
        e.preventDefault();
        const button = $(this);
        const citationText = button.data('citation-text');

        const tempTextarea = $('<textarea>');
        tempTextarea.val($('<div>').html(citationText).text()); // Decode HTML entities
        $('body').append(tempTextarea);
        tempTextarea.select();

        try {
            document.execCommand('copy');
            button.text('Copied!').css('background-color', '#28a745');
            setTimeout(() => {
                button.text('Copy').css('background-color', '');
            }, 2000);
        } catch (err) {
            alert('Failed to copy citation.');
        }

        tempTextarea.remove();
    });

    // Delete citation
    $(document).on('click', '.hs-delete-citation', function(e) {
        e.preventDefault();
        if (!confirm('Are you sure you want to delete this citation?')) {
            return;
        }

        const button = $(this);
        const citationId = button.data('citation-id');
        const bookId = $('#hs-citation-book-id').val();

        $.ajax({
            url: hs_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'hs_delete_citation',
                nonce: hs_ajax.nonce,
                citation_id: citationId
            },
            beforeSend: function() {
                button.text('Deleting...').prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    // Reload citations list
                    loadSavedCitations(bookId);
                } else {
                    alert('Failed to delete citation: ' + response.data.message);
                    button.text('Delete').prop('disabled', false);
                }
            },
            error: function() {
                alert('Failed to delete citation.');
                button.text('Delete').prop('disabled', false);
            }
        });
    });

}); // <-- This is the one, final closing bracket for jQuery(document).ready()
