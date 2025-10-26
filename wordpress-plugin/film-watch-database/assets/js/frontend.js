/**
 * Film Watch Database - Frontend JavaScript
 */

(function($) {
    'use strict';

    /**
     * Initialize search functionality
     */
    function initSearch() {
        const searchBtn = document.getElementById('fwd-search-btn');
        const searchInput = document.getElementById('fwd-search-input');

        if (!searchBtn || !searchInput) return;

        // Search on button click
        searchBtn.addEventListener('click', performSearch);

        // Search on Enter key
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                performSearch();
            }
        });
    }

    /**
     * Perform search via AJAX
     */
    function performSearch() {
        const searchType = document.getElementById('fwd-search-type');
        const searchInput = document.getElementById('fwd-search-input');
        const resultsContainer = document.getElementById('fwd-search-results');
        const searchBtn = document.getElementById('fwd-search-btn');

        if (!searchType || !searchInput || !resultsContainer) return;

        const queryType = searchType.value;
        const searchTerm = searchInput.value.trim();

        if (!searchTerm) {
            resultsContainer.innerHTML = '<div class="fwd-error">Please enter a search term.</div>';
            return;
        }

        // Show loading state
        searchBtn.disabled = true;
        searchBtn.textContent = 'Searching...';
        resultsContainer.innerHTML = '<div class="fwd-loading">Searching database...</div>';

        // Make AJAX request
        $.ajax({
            url: fwdAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'fwd_search',
                nonce: fwdAjax.nonce,
                query_type: queryType,
                search_term: searchTerm
            },
            success: function(response) {
                if (response.success) {
                    displayResults(response.data, queryType, resultsContainer);
                } else {
                    resultsContainer.innerHTML = '<div class="fwd-error">Error: ' +
                        (response.data.error || 'Unknown error occurred') + '</div>';
                }
            },
            error: function(xhr, status, error) {
                resultsContainer.innerHTML = '<div class="fwd-error">Network error: ' + error + '</div>';
            },
            complete: function() {
                searchBtn.disabled = false;
                searchBtn.textContent = 'Search';
            }
        });
    }

    /**
     * Display search results
     */
    function displayResults(data, queryType, container) {
        if (data.count === 0) {
            container.innerHTML = '<div class="fwd-no-results">No results found.</div>';
            return;
        }

        let html = '<div class="fwd-success">Found ' + data.count + ' result(s)</div>';
        html += '<div class="fwd-items-list">';

        if (queryType === 'actor' && data.films) {
            data.films.forEach(function(film) {
                html += buildActorResultHTML(film);
            });
        } else if (queryType === 'brand' && data.films) {
            data.films.forEach(function(film) {
                html += buildBrandResultHTML(film);
            });
        } else if (queryType === 'film' && data.watches) {
            data.watches.forEach(function(watch) {
                html += buildFilmResultHTML(watch);
            });
        }

        html += '</div>';
        container.innerHTML = html;
    }

    /**
     * Build HTML for actor search result
     */
    function buildActorResultHTML(film) {
        let sourceHtml = '';
        if (film.source_url) {
            sourceHtml = `<br><strong>Source:</strong> <a href="${escapeHtml(film.source_url)}" target="_blank" rel="noopener">View Reference</a>`;
        }
        return `
            <div class="fwd-item">
                <div class="fwd-item-title">${escapeHtml(film.title)} (${escapeHtml(film.year)})</div>
                <div class="fwd-item-details">
                    <strong>Character:</strong> ${escapeHtml(film.character)}<br>
                    <strong>Watch:</strong> ${escapeHtml(film.brand)} ${escapeHtml(film.model)}<br>
                    <strong>Role:</strong> ${escapeHtml(film.narrative)}${sourceHtml}
                </div>
            </div>
        `;
    }

    /**
     * Build HTML for brand search result
     */
    function buildBrandResultHTML(film) {
        let sourceHtml = '';
        if (film.source_url) {
            sourceHtml = `<br><strong>Source:</strong> <a href="${escapeHtml(film.source_url)}" target="_blank" rel="noopener">View Reference</a>`;
        }
        return `
            <div class="fwd-item">
                <div class="fwd-item-title">${escapeHtml(film.title)} (${escapeHtml(film.year)})</div>
                <div class="fwd-item-details">
                    <strong>Actor:</strong> ${escapeHtml(film.actor)} as ${escapeHtml(film.character)}<br>
                    <strong>Watch:</strong> ${escapeHtml(film.model)}<br>
                    <strong>Role:</strong> ${escapeHtml(film.narrative)}${sourceHtml}
                </div>
            </div>
        `;
    }

    /**
     * Build HTML for film search result
     */
    function buildFilmResultHTML(watch) {
        let sourceHtml = '';
        if (watch.source_url) {
            sourceHtml = `<br><strong>Source:</strong> <a href="${escapeHtml(watch.source_url)}" target="_blank" rel="noopener">View Reference</a>`;
        }
        return `
            <div class="fwd-item">
                <div class="fwd-item-title">${escapeHtml(watch.actor)} as ${escapeHtml(watch.character)}</div>
                <div class="fwd-item-details">
                    <strong>Watch:</strong> ${escapeHtml(watch.brand)} ${escapeHtml(watch.model)}<br>
                    <strong>Role:</strong> ${escapeHtml(watch.narrative)}${sourceHtml}
                </div>
            </div>
        `;
    }

    /**
     * Initialize add entry form
     */
    function initAddForm() {
        const addBtn = document.getElementById('fwd-add-btn');

        if (!addBtn) return;

        addBtn.addEventListener('click', addEntry);
    }

    /**
     * Add new entry via AJAX
     */
    function addEntry() {
        const entryText = document.getElementById('fwd-entry-text');
        const narrative = document.getElementById('fwd-narrative');
        const sourceUrl = document.getElementById('fwd-source-url');
        const resultDiv = document.getElementById('fwd-add-result');
        const addBtn = document.getElementById('fwd-add-btn');

        if (!entryText || !narrative || !resultDiv) return;

        const entryValue = entryText.value.trim();
        const narrativeValue = narrative.value.trim();
        const sourceUrlValue = sourceUrl ? sourceUrl.value.trim() : '';

        if (!entryValue) {
            showResult(resultDiv, 'fwd-error', 'Please enter an entry text.');
            return;
        }

        // Show loading state
        addBtn.disabled = true;
        addBtn.textContent = 'Adding...';
        showResult(resultDiv, 'fwd-loading', 'Adding entry to database...');

        // Make AJAX request
        $.ajax({
            url: fwdAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'fwd_add_entry',
                nonce: fwdAjax.nonce,
                entry_text: entryValue,
                narrative: narrativeValue,
                source_url: sourceUrlValue
            },
            success: function(response) {
                if (response.success) {
                    showResult(resultDiv, 'fwd-success', '✓ ' + response.data.message);
                    entryText.value = '';
                    narrative.value = '';
                    if (sourceUrl) sourceUrl.value = '';
                } else if (response.data && response.data.duplicate) {
                    // Show duplicate comparison UI
                    showDuplicateComparison(resultDiv, response.data, entryValue, narrativeValue, sourceUrlValue);
                } else {
                    showResult(resultDiv, 'fwd-error', 'Error: ' +
                        (response.data.error || 'Unknown error occurred'));
                }
            },
            error: function(xhr, status, error) {
                showResult(resultDiv, 'fwd-error', 'Network error: ' + error);
            },
            complete: function() {
                addBtn.disabled = false;
                addBtn.textContent = 'Add to Database';
            }
        });
    }

    /**
     * Show result message
     */
    function showResult(element, className, message) {
        element.className = 'fwd-result show ' + className;
        element.textContent = message;
    }

    /**
     * Show duplicate comparison UI
     */
    function showDuplicateComparison(element, data, entryText, narrative, sourceUrl) {
        const existing = data.existing;
        const newEntry = data.new;

        let html = '<div class="fwd-duplicate-warning">';
        html += '<h4>⚠️ Duplicate Entry Detected</h4>';
        html += '<p>' + escapeHtml(data.error) + '</p>';
        html += '<div class="fwd-comparison">';

        // Existing entry
        html += '<div class="fwd-existing-entry">';
        html += '<h5>Current Entry:</h5>';
        html += '<strong>Watch:</strong> ' + escapeHtml(existing.brand) + ' ' + escapeHtml(existing.model) + '<br>';
        html += '<strong>Character:</strong> ' + escapeHtml(existing.character) + '<br>';
        if (existing.narrative) {
            html += '<strong>Narrative:</strong> ' + escapeHtml(existing.narrative) + '<br>';
        }
        if (existing.source_url) {
            html += '<strong>Source:</strong> <a href="' + escapeHtml(existing.source_url) + '" target="_blank">View</a><br>';
        }
        html += '</div>';

        // New entry
        html += '<div class="fwd-new-entry">';
        html += '<h5>New Entry:</h5>';
        html += '<strong>Watch:</strong> ' + escapeHtml(newEntry.brand) + ' ' + escapeHtml(newEntry.model) + '<br>';
        html += '<strong>Character:</strong> ' + escapeHtml(newEntry.character) + '<br>';
        if (narrative) {
            html += '<strong>Narrative:</strong> ' + escapeHtml(narrative) + '<br>';
        }
        if (sourceUrl) {
            html += '<strong>Source:</strong> <a href="' + escapeHtml(sourceUrl) + '" target="_blank">View</a><br>';
        }
        html += '</div>';

        html += '</div>'; // end comparison
        html += '<div class="fwd-duplicate-actions">';
        html += '<button id="fwd-update-btn" class="fwd-button fwd-button-update" data-faw-id="' + existing.faw_id + '">Update with New Entry</button>';
        html += '<button id="fwd-cancel-btn" class="fwd-button fwd-button-secondary">Cancel</button>';
        html += '</div>';
        html += '</div>';

        element.className = 'fwd-result show';
        element.innerHTML = html;

        // Store the form values for later use (avoid escaping issues with data attributes)
        element.dataset.pendingEntryText = entryText;
        element.dataset.pendingNarrative = narrative;
        element.dataset.pendingSourceUrl = sourceUrl;

        // Attach event handlers
        document.getElementById('fwd-update-btn').addEventListener('click', handleUpdate);
        document.getElementById('fwd-cancel-btn').addEventListener('click', function() {
            element.innerHTML = '';
            element.className = 'fwd-result';
            delete element.dataset.pendingEntryText;
            delete element.dataset.pendingNarrative;
            delete element.dataset.pendingSourceUrl;
        });
    }

    /**
     * Handle update entry
     */
    function handleUpdate(e) {
        const btn = e.target;
        const fawId = btn.getAttribute('data-faw-id');
        const resultDiv = document.getElementById('fwd-add-result');
        const entryText = resultDiv.dataset.pendingEntryText;
        const narrative = resultDiv.dataset.pendingNarrative;
        const sourceUrl = resultDiv.dataset.pendingSourceUrl;

        btn.disabled = true;
        btn.textContent = 'Updating...';

        $.ajax({
            url: fwdAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'fwd_update_entry',
                nonce: fwdAjax.nonce,
                faw_id: fawId,
                entry_text: entryText,
                narrative: narrative,
                source_url: sourceUrl
            },
            success: function(response) {
                if (response.success) {
                    showResult(resultDiv, 'fwd-success', '✓ ' + response.data.message);
                    // Clear form fields
                    document.getElementById('fwd-entry-text').value = '';
                    document.getElementById('fwd-narrative').value = '';
                    const sourceUrlField = document.getElementById('fwd-source-url');
                    if (sourceUrlField) sourceUrlField.value = '';
                } else {
                    showResult(resultDiv, 'fwd-error', 'Error: ' + (response.data.error || 'Unknown error occurred'));
                }
            },
            error: function(xhr, status, error) {
                showResult(resultDiv, 'fwd-error', 'Network error: ' + error);
            }
        });
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        initSearch();
        initAddForm();
    });

})(jQuery);
