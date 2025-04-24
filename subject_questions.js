document.addEventListener('DOMContentLoaded', function() {
    // Tab functionality
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            // Remove active class from all buttons and contents
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Add active class to clicked button and corresponding content
            button.classList.add('active');
            const tabId = button.getAttribute('data-tab');
            document.getElementById(`${tabId}-tab`).classList.add('active');
            
            // Load content if needed (for groups and difficulty tabs)
            if (tabId !== 'all') {
                loadTabContent(tabId);
            }
        });
    });
    
    // Bulk actions
    const bulkActionSelect = document.getElementById('bulk-action');
    const applyBulkActionBtn = document.getElementById('apply-bulk-action');
    const modal = document.getElementById('bulk-modal');
    const closeModalButtons = document.querySelectorAll('.close-modal');
    const confirmBulkActionBtn = document.getElementById('confirm-bulk-action');
    
    applyBulkActionBtn.addEventListener('click', () => {
        const action = bulkActionSelect.value;
        const selectedQuestions = getSelectedQuestions();
        
        if (action === '' || selectedQuestions.length === 0) {
            alert('Please select an action and at least one question');
            return;
        }
        
        openBulkModal(action, selectedQuestions);
    });
    
    closeModalButtons.forEach(button => {
        button.addEventListener('click', () => {
            modal.style.display = 'none';
        });
    });
    
    window.addEventListener('click', (event) => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
    
    // Confirm bulk action
    confirmBulkActionBtn.addEventListener('click', () => {
        const action = document.getElementById('modal-title').getAttribute('data-action');
        const selectedQuestions = document.getElementById('modal-title').getAttribute('data-questions').split(',');
        
        switch(action) {
            case 'delete':
                deleteQuestions(selectedQuestions);
                break;
            case 'change-difficulty':
                const difficulty = document.getElementById('bulk-difficulty').value;
                updateQuestionDifficulty(selectedQuestions, difficulty);
                break;
            case 'group':
                const groupNum = document.getElementById('bulk-group-num').value;
                addQuestionsToGroup(selectedQuestions, groupNum);
                break;
            case 'export':
                exportQuestions(selectedQuestions);
                break;
        }
        
        modal.style.display = 'none';
    });
    
    // Quick delete buttons
    document.querySelectorAll('.quick-delete').forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this question?')) {
                e.preventDefault();
            }
        });
    });
});

function getSelectedQuestions() {
    const checkboxes = document.querySelectorAll('.question-checkbox:checked');
    return Array.from(checkboxes).map(checkbox => checkbox.value);
}

function openBulkModal(action, questionIds) {
    const modalTitle = document.getElementById('modal-title');
    const modalBody = document.getElementById('modal-body');
    
    modalTitle.setAttribute('data-action', action);
    modalTitle.setAttribute('data-questions', questionIds.join(','));
    
    switch(action) {
        case 'delete':
            modalTitle.textContent = 'Confirm Delete';
            modalBody.innerHTML = `
                <p>You are about to delete ${questionIds.length} question(s). This action cannot be undone.</p>
                <p>Are you sure you want to proceed?</p>
            `;
            break;
            
        case 'change-difficulty':
            modalTitle.textContent = 'Change Difficulty';
            modalBody.innerHTML = `
                <p>You are changing the difficulty for ${questionIds.length} question(s).</p>
                <div class="form-group">
                    <label for="bulk-difficulty">New Difficulty:</label>
                    <select id="bulk-difficulty" class="form-control">
                        <option value="1">Easy</option>
                        <option value="2">Medium</option>
                        <option value="3">Hard</option>
                    </select>
                </div>
            `;
            break;
            
        case 'group':
            modalTitle.textContent = 'Add to Group';
            modalBody.innerHTML = `
                <p>You are adding ${questionIds.length} question(s) to a group.</p>
                <div class="form-group">
                    <label for="bulk-group-num">Group Number:</label>
                    <input type="number" id="bulk-group-num" class="form-control" min="1" required>
                </div>
                <p class="text-muted">Enter 0 to remove from group</p>
            `;
            break;
            
        case 'export':
            modalTitle.textContent = 'Export Questions';
            modalBody.innerHTML = `
                <p>You are about to export ${questionIds.length} question(s).</p>
                <div class="form-group">
                    <label for="export-format">Format:</label>
                    <select id="export-format" class="form-control">
                        <option value="pdf">PDF</option>
                        <option value="word">Word Document</option>
                        <option value="text">Plain Text</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="include-answers">Include Answers:</label>
                    <input type="checkbox" id="include-answers" checked>
                </div>
            `;
            break;
    }
    
    modal.style.display = 'block';
}

function deleteQuestions(questionIds) {
    // AJAX call to delete questions
    fetch('ajax/delete_questions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ question_ids: questionIds })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove deleted questions from DOM
            questionIds.forEach(id => {
                document.querySelector(`.question-card[data-id="${id}"]`).remove();
            });
            alert(`${questionIds.length} question(s) deleted successfully`);
        } else {
            alert('Error deleting questions: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting questions');
    });
}

function updateQuestionDifficulty(questionIds, difficulty) {
    // AJAX call to update difficulty
    fetch('ajax/update_difficulty.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ 
            question_ids: questionIds,
            difficulty: difficulty
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update difficulty badges in DOM
            questionIds.forEach(id => {
                const card = document.querySelector(`.question-card[data-id="${id}"]`);
                if (card) {
                    const badge = card.querySelector('.difficulty-badge');
                    badge.className = `difficulty-badge difficulty-${difficulty}`;
                    badge.textContent = ['Easy', 'Medium', 'Hard'][difficulty - 1];
                }
            });
            alert('Difficulty updated successfully');
        } else {
            alert('Error updating difficulty: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating difficulty');
    });
}

function addQuestionsToGroup(questionIds, groupNum) {
    // AJAX call to add questions to group
    fetch('ajax/update_group.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ 
            question_ids: questionIds,
            group_num: groupNum
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update group badges in DOM
            questionIds.forEach(id => {
                const card = document.querySelector(`.question-card[data-id="${id}"]`);
                if (card) {
                    let groupBadge = card.querySelector('.group-badge');
                    
                    if (groupNum > 0) {
                        if (!groupBadge) {
                            groupBadge = document.createElement('span');
                            groupBadge.className = 'group-badge';
                            card.querySelector('.card-header').appendChild(groupBadge);
                        }
                        groupBadge.textContent = `Group ${groupNum}`;
                    } else if (groupBadge) {
                        groupBadge.remove();
                    }
                }
            });
            alert('Group updated successfully');
        } else {
            alert('Error updating group: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating group');
    });
}

function exportQuestions(questionIds) {
    const format = document.getElementById('export-format').value;
    const includeAnswers = document.getElementById('include-answers').checked;
    
    // Create a form and submit it to trigger download
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'export_questions.php';
    
    const idsInput = document.createElement('input');
    idsInput.type = 'hidden';
    idsInput.name = 'question_ids';
    idsInput.value = questionIds.join(',');
    form.appendChild(idsInput);
    
    const formatInput = document.createElement('input');
    formatInput.type = 'hidden';
    formatInput.name = 'format';
    formatInput.value = format;
    form.appendChild(formatInput);
    
    const answersInput = document.createElement('input');
    answersInput.type = 'hidden';
    answersInput.name = 'include_answers';
    answersInput.value = includeAnswers ? '1' : '0';
    form.appendChild(answersInput);
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function loadTabContent(tabId) {
    const subjectId = new URLSearchParams(window.location.search).get('subject_id');
    
    // Show loading state
    const tabContent = document.getElementById(`${tabId}-tab`);
    tabContent.innerHTML = '<div class="loading">Loading...</div>';
    
    // Load content via AJAX
    fetch(`ajax/load_tab.php?tab=${tabId}&subject_id=${subjectId}`)
    .then(response => response.text())
    .then(html => {
        tabContent.innerHTML = html;
    })
    .catch(error => {
        console.error('Error loading tab content:', error);
        tabContent.innerHTML = '<div class="error">Error loading content</div>';
    });
}

function renderMath() {
    if (typeof MathJax !== 'undefined') {
        MathJax.typesetPromise().then(() => {
            document.body.classList.add('mathjax-processed');
        }).catch((err) => {
            console.error('MathJax typesetting error:', err);
        });
    }
}

// Call this after loading new content:
document.addEventListener('DOMContentLoaded', renderMath);

// For AJAX-loaded content:
function loadQuestionContent() {
    // Your content loading code...
    renderMath();
}

function renderQuestionText($text) {
    // Convert \[ and \] to MathJax delimiters if needed
    $text = htmlspecialchars($text);
    // Preserve LaTeX content (don't escape \( \) \[ \])
    $text = preg_replace('/\\\\([()\\[\\]])/', '\\\\$1', $text);
    return $text;
}
