/**
 * RETRO 90s TEMPLATE JAVASCRIPT
 * Compatible with Netscape Navigator 4.0+ and Internet Explorer 4.0+
 * 
 * WARNING: This code may cause nostalgia!
 */

// Global variables (90s style!)
var selectedFiles = [];
var isPreviewOpen = false;

// Initialize when page loads (old school way)
$(document).ready(function() {
    console.log("🌟 Welcome to the World Wide Web! 🌟");
    
    // Add some retro flair
    addRetroEffects();
    
    // Initialize event handlers
    initializeEventHandlers();
    
    // Welcome message in console instead of alert (less intrusive)
    console.log("🌟 Welcome to this AWESOME website! Ready to surf the information superhighway! 🌟");
});

// Add retro effects to the page
function addRetroEffects() {
    // Add hover sound effects (commented out to avoid annoyance)
    /*
    $('a').hover(function() {
        // playSound('beep.wav');
    });
    */
    
    // Make tables more interactive
    $('tr').hover(
        function() {
            $(this).css('cursor', 'pointer');
        },
        function() {
            $(this).css('cursor', 'default');
        }
    );
    
    // Add some sparkle to important elements
    setTimeout(function() {
        $('blink').each(function() {
            var $elem = $(this);
            var originalText = $elem.text();
            setInterval(function() {
                if (Math.random() > 0.5) {
                    $elem.html('★ ' + originalText + ' ★');
                } else {
                    $elem.html(originalText);
                }
            }, 2000);
        });
    }, 2000);
}

// Initialize event handlers (90s DOM style)
function initializeEventHandlers() {
    // Checkbox selection handling
    $('.batch_checkbox').change(function() {
        updateBulkActions();
    });

    // Preview link handling
    $('.preview-link').click(function(e) {
        e.preventDefault();
        var url = $(this).data('url');
        showPreview(url);
    });

    // ESC key to close modal (very important for user experience!)
    $(document).keydown(function(e) {
        if (e.keyCode === 27 && isPreviewOpen) { // ESC key
            closePreview();
        }
    });

    // Click background to close modal (modern UX in 90s style!)
    $(document).on('click', '#previewModal', function(e) {
        // Close if clicking outside the modal content
        if (!$(e.target).closest('#previewModalContent').length) {
            closePreview();
        }
    });
    
    // Form submission with classic confirmation
    $('form[name="form_search"]').submit(function() {
        var searchTerm = $('input[name="search"]').val();
        if (searchTerm === '') {
            alert('Please enter a search term before clicking GO!');
            return false;
        }
        
        // Classic loading message
        $('input[type="submit"]').val('Searching...');
        return true;
    });
    
    // Removed right-click protection - too annoying for modern users
    // Keep the 90s feel without the user experience friction
    
    // Removed the annoying beforeunload warning - too intrusive for modern users
    // Classic 90s websites had this, but it's better UX without it
}

// Update bulk actions display
function updateBulkActions() {
    var checkedBoxes = $('.batch_checkbox:checked');
    var count = checkedBoxes.length;
    
    if (count > 0) {
        $('#bulk-actions-table').show();
        $('#selected-count').text(count);
        
        // Update selected files array
        selectedFiles = [];
        checkedBoxes.each(function() {
            selectedFiles.push($(this).val());
        });
        
        // Add some 90s flair
        if (count === 1) {
            $('#selected-count').parent().html('<b>Selected: ' + count + ' file</b> <blink>✓</blink> <input type="button" value="Clear All" onclick="clearSelection()" /> <input type="button" value="Download Zip" onclick="downloadSelected()" />');
        } else {
            $('#selected-count').parent().html('<b>Selected: ' + count + ' files</b> <blink>✓✓✓</blink> <input type="button" value="Clear All" onclick="clearSelection()" /> <input type="button" value="Download Zip" onclick="downloadSelected()" />');
        }
    } else {
        $('#bulk-actions-table').hide();
        selectedFiles = [];
    }
}

// Clear selection (classic function style)
function clearSelection() {
    $('.batch_checkbox').prop('checked', false);
    updateBulkActions();
    
    // 90s confirmation
    alert('Selection cleared! Ready for more file action! 💾');
}

// Download selected files
function downloadSelected() {
    if (selectedFiles.length === 0) {
        alert('ERROR: No files selected! Please select some files first! 🚫');
        return;
    }
    
    // Classic 90s confirmation dialog
    var message = 'You are about to download ' + selectedFiles.length + ' file(s) in a ZIP archive.\n\n';
    message += 'This may take a while depending on your connection speed.\n';
    message += 'Are you ready to proceed? 📦';
    
    if (confirm(message)) {
        // Show classic loading message
        alert('Preparing your download... Please wait! ⏳');
        
        // Create form and submit (90s way)
        var form = $('<form>', {
            method: 'POST',
            action: window.base_url + 'process.php'
        });
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'do',
            value: 'zip'
        }));
        
        for (var i = 0; i < selectedFiles.length; i++) {
            form.append($('<input>', {
                type: 'hidden',
                name: 'files[]',
                value: selectedFiles[i]
            }));
        }
        
        $('body').append(form);
        form.submit();
        
        // Classic success message
        setTimeout(function() {
            alert('Download initiated! Check your Downloads folder! 🎉');
        }, 1000);
    }
}

// Show preview modal (90s style)
function showPreview(url) {
    if (isPreviewOpen) {
        alert('A preview window is already open! Please close it first! 🖼️');
        return;
    }
    
    isPreviewOpen = true;
    
    // Show loading message in classic 90s style
    $('#previewContent').html('<center><font face="Arial, sans-serif" size="2"><b>Loading preview...</b><br><img src="data:image/gif;base64,R0lGODlhEAAQAPIAAP///wAAAMLCwkJCQgAAAGJiYoKCgpKSkiH/C05FVFNDQVBFMi4wAwEAAAAh/hpDcmVhdGVkIHdpdGggYWpheGxvYWQuaW5mbwAh+QQJCgAAACwAAAAAEAAQAAADMwi63P4wyklrE2MIOggZnAdOmGYJRbExwroUmcG2LmDEwnHQLVsYOd2mBzkYDAdKa+dIAAAh+QQJCgAAACwAAAAAEAAQAAADNAi63P5OjCEgG4QMu7DmikRxQlFUYDEZIGBMRVsaqHwctXXf7WEYB4Ag1xjihkMZsiUkKhIAIfkECQoAAAAsAAAAABAAEAAAAzYIujIjK8pByJDMlFYvBoVjHA70GU7xSUJhmKtwHPAKzLO9HMaoKwJZ7Rf8AYPDDzKpZBqfvwQAIfkECQoAAAAsAAAAABAAEAAAAzMIumIlK8oyhpHsnFZfhYumCYUhDAQxRIdhHBGqRoKw0R8DYlJd8z0fMDgsGo/IpHI5TAAAIfkECQoAAAAsAAAAABAAEAAAAzIIunInK0rnZBTwGPNMgQwmdsNgXGJUlIWEuR5oWUIpz8pAEAMe6TwfwyYsGo/IpFKSAAAh+QQJCgAAACwAAAAAEAAQAAADMwi6IMKQORfjdOe82p4wGccc4CEuQradylesojEMBgsUc2G7sDX3lQGBMLAJibufbSlKAAAh+QQJCgAAACwAAAAAEAAQAAADMgi63P7wjRLl3+" width="16" height="16" alt="Loading..."></font></center>');
    $('#previewModal').show();
    
    // Make AJAX request (but with 90s styling)
    $.ajax({
        method: "GET",
        url: url,
        cache: false,
        timeout: 30000 // 30 second timeout like dial-up days
    }).done(function(response) {
        try {
            var obj = JSON.parse(response);
            var content = createRetroPreviewContent(obj);
            $('#previewContent').html(content);
        } catch (error) {
            $('#previewContent').html('<center><font face="Arial, sans-serif" size="2" color="#ff0000"><b>ERROR: Could not load preview! 🚫</b><br>Please try again later.</font></center>');
        }
    }).fail(function() {
        $('#previewContent').html('<center><font face="Arial, sans-serif" size="2" color="#ff0000"><b>NETWORK ERROR! 📡</b><br>Check your internet connection and try again!</font></center>');
    });
}

// Create retro preview content
function createRetroPreviewContent(obj) {
    var content = '';
    
    // Add classic 90s header
    content += '<center><font face="Arial, sans-serif" size="2"><b>' + obj.name + '</b></font></center><br>';
    
    switch (obj.type) {
        case 'video':
            content += '<center>';
            content += '<video controls style="max-width: 100%; border: 2px inset #c0c0c0;">';
            content += '<source src="' + obj.file_url + '" type="' + obj.mime_type + '">';
            content += 'Your browser does not support video playback! 📹';
            content += '</video>';
            content += '<br><font face="Arial, sans-serif" size="1">💡 TIP: You may need to install additional codecs!</font>';
            content += '</center>';
            break;
            
        case 'audio':
            content += '<center>';
            content += '<audio controls style="width: 100%; border: 2px inset #c0c0c0;">';
            content += '<source src="' + obj.file_url + '" type="' + obj.mime_type + '">';
            content += 'Your browser does not support audio playback! 🎵';
            content += '</audio>';
            content += '<br><font face="Arial, sans-serif" size="1">🎧 Turn up your speakers for the best experience!</font>';
            content += '</center>';
            break;
            
        case 'pdf':
            content += '<center>';
            content += '<iframe src="' + obj.file_url + '" style="width: 100%; height: 400px; border: 2px inset #c0c0c0;" title="PDF Document">';
            content += 'Your browser cannot display PDF files! You need Adobe Acrobat Reader! 📕';
            content += '</iframe>';
            content += '<br><font face="Arial, sans-serif" size="1">📄 Download <a href="http://www.adobe.com" target="_blank">Adobe Acrobat Reader</a> for better PDF support!</font>';
            content += '</center>';
            break;
            
        case 'image':
            content += '<center>';
            content += '<img src="' + obj.file_url + '" style="max-width: 100%; border: 2px inset #c0c0c0;" alt="' + obj.name + '">';
            content += '<br><font face="Arial, sans-serif" size="1">🖼️ Image loaded successfully! Right-click and "Save As" to download!</font>';
            content += '</center>';
            break;
            
        default:
            content += '<center>';
            content += '<font face="Arial, sans-serif" size="3" color="#ff0000"><b>⚠️ UNSUPPORTED FILE TYPE ⚠️</b></font><br><br>';
            content += '<font face="Arial, sans-serif" size="2">This file type cannot be previewed in your browser.</font><br>';
            content += '<font face="Arial, sans-serif" size="2">You may need to download it and open with an appropriate application.</font><br><br>';
            content += '<a href="' + obj.file_url + '" target="_blank">';
            content += '<img src="' + window.base_url + 'templates/retro90s/images/download.gif" alt="Download File" border="0">';
            content += '</a>';
            content += '</center>';
    }
    
    return content;
}

// Close preview modal
function closePreview() {
    $('#previewModal').hide();
    $('#previewContent').html('');
    isPreviewOpen = false;
    
    // Classic 90s goodbye message
    console.log("Preview closed! Thanks for viewing! 👋");
}

// Classic 90s easter eggs
function konamiCode() {
    var sequence = [];
    var konamiSequence = [38, 38, 40, 40, 37, 39, 37, 39, 66, 65]; // ↑↑↓↓←→←→BA
    
    $(document).keydown(function(e) {
        sequence.push(e.keyCode);
        if (sequence.length > konamiSequence.length) {
            sequence.shift();
        }
        
        if (sequence.join(',') === konamiSequence.join(',')) {
            alert('🎮 KONAMI CODE ACTIVATED! 🎮\n\nYou found the secret! You are a true 90s kid! 🌟');
            $('body').css('background-image', 'url(data:image/gif;base64,R0lGODlhEAAQAKIAAP///8zMzJmZmWZmZjMzMwAAAAAAAAAAACH5BAEAAAUALAAAAAAQABAAAAMvWLrc/jDKSau9OOvNu/9gKI5kaZ5oqq5s675wLM90bd94ru987//AoHBILBqPyKRyuSxqAAAh+QQBAAACACwAAAAAEAAQAAADGWi63P4wykmrvTjrzbv/YCiOZGmeaKqubOsOAAAh+QQBAAACACwAAAAAEAAQAAADGWi63P4wykmrvTjrzbv/YCiOZGmeaKqubOsOAAAh+QQBAAACACwAAAAAEAAQAAADGWi63P4wykmrvTjrzbv/YCiOZGmeaKqubOsOAAA7)');
            sequence = [];
        }
    });
}

// Initialize easter eggs
$(document).ready(function() {
    konamiCode();
    
    // Secret double-click on logo
    $('font:contains("★")').dblclick(function() {
        alert('🌟 DOUBLE-CLICK DETECTED! 🌟\n\nYou have excellent mouse skills! Very 90s! 🖱️');
    });
});

// Status bar messages (like old browsers)
function setStatus(message) {
    console.log('Status: ' + message);
    // In a real 90s browser, this would show in the status bar
}

// Classic form validation (very important in the 90s!)
function validateForm(form) {
    var isValid = true;
    var errors = [];
    
    $(form).find('input[type="text"]').each(function() {
        if ($(this).attr('required') && $(this).val() === '') {
            errors.push('Field "' + ($(this).attr('name') || 'Unknown') + '" is required!');
            isValid = false;
        }
    });
    
    if (!isValid) {
        alert('FORM VALIDATION ERROR!\n\n' + errors.join('\n') + '\n\nPlease correct these errors and try again! ❌');
    }
    
    return isValid;
}

// Add some fun 90s functionality
$(document).ready(function() {
    // Add visitor counter (fake, but very 90s)
    var visitorCount = Math.floor(Math.random() * 999999) + 100000;
    console.log('🔢 You are visitor #' + visitorCount + '! Welcome to our website! 🔢');
    
    // Add current date and time (very important in the 90s)
    var now = new Date();
    console.log('📅 Current date and time: ' + now.toString());
    
    // Add "best viewed in" message
    console.log('💻 This website is best viewed in 800x600 resolution with 16-bit color! 💻');
});

// Classic window focus events
$(window).focus(function() {
    document.title = document.title.replace('★ ', '');
    console.log('Welcome back! 👋');
});

$(window).blur(function() {
    document.title = '★ ' + document.title;
    console.log('Don\'t leave us! Come back! 😢');
});

// Auto-scroll function (for those long pages)
function autoScroll() {
    if (confirm('Would you like to auto-scroll through this page?\n\n(This was a popular feature in the 90s!)')) {
        var scrollSpeed = 50; // pixels per second
        var scrollStep = 1;
        var scrollInterval = setInterval(function() {
            window.scrollBy(0, scrollStep);
            if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight) {
                clearInterval(scrollInterval);
                alert('Auto-scroll complete! Thanks for reading! 📖');
            }
        }, 1000 / scrollSpeed);
    }
}

// Make it global for easy access
window.clearSelection = clearSelection;
window.downloadSelected = downloadSelected;
window.closePreview = closePreview;
window.autoScroll = autoScroll;