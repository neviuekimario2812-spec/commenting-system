// Behaviour for the comment area on the home page.
// (The header, login check and logout button are handled by app.js.)

const MAX_COMMENT_LENGTH = 1000;
const MAX_IMAGE_SIZE = 5 * 1024 * 1024;   // 5 MB
const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

const composerAvatar = document.getElementById('composer-avatar');
const commentForm = document.getElementById('comment-form');
const commentInput = document.getElementById('comment-text');
const commentCounter = document.getElementById('comment-counter');
const commentList = document.getElementById('comment-list');
const emptyState = document.getElementById('empty-state');
const postButton = commentForm.querySelector('button[type="submit"]');

const imageInput = document.getElementById('comment-image');
const imagePreview = document.getElementById('image-preview');
const imagePreviewImg = document.getElementById('image-preview-img');
const removeImageButton = document.getElementById('remove-image');

let previewUrl = null;   // temporary address of the image being previewed

// A small helper: creates an element, gives it a CSS class, and (optionally) text.
// textContent is safe: it shows the text exactly as written and never runs it as HTML.
function makeElement(tag, className, text) {
    const element = document.createElement(tag);

    if (className) {
        element.className = className;
    }

    if (text !== undefined) {
        element.textContent = text;
    }

    return element;
}

// Turns a date from the server into friendly text, e.g. "Nov 8, 2026, 10:15 AM".
function formatDate(value) {
    const date = new Date(value);

    if (isNaN(date.getTime())) {
        return '';   // the date could not be understood
    }

    return date.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}

// Builds a <button> that does not submit a form.
function makeButton(className, text) {
    const button = makeElement('button', className, text);
    button.type = 'button';

    return button;
}

// Builds ONE comment card from a comment object and returns it.
function createCommentCard(comment) {
    const card = makeElement('article', 'comment-card');

    // Top row: picture, then name and date underneath each other
    const header = makeElement('div', 'comment-header');

    const avatar = makeElement('img', 'avatar');
    avatar.src = avatarUrl(comment.profile_picture);
    avatar.alt = comment.display_name + "'s profile picture";

    const meta = makeElement('div', 'comment-meta');
    const author = makeElement('span', 'comment-author', comment.display_name);
    const dateText = formatDate(comment.created_at) + (comment.is_edited ? ' \u00b7 edited' : '');
    const date = makeElement('span', 'comment-date', dateText);

    meta.append(author, date);
    header.append(avatar, meta);
    card.append(header);

    // The body holds the text (or the editor while the comment is being edited).
    const body = makeElement('div', 'comment-body');

    if (comment.content) {
        body.append(makeElement('p', 'comment-text', comment.content));
    }

    card.append(body);

    // The comment image (text-only comments have none)
    if (comment.image_path) {
        const image = makeElement('img', 'comment-image');
        image.src = comment.image_path;
        image.alt = 'Image posted by ' + comment.display_name;
        image.loading = 'lazy';   // the browser loads it only when it is about to be seen
        card.append(image);
    }

    // Edit and Delete buttons: only for the comment's owner.
    // (This only hides the buttons. The server checks ownership again.)
    if (comment.is_owner) {
        const actions = makeElement('div', 'comment-actions');
        const editButton = makeButton('action-button', 'Edit');
        const deleteButton = makeButton('action-button danger', 'Delete');

        editButton.addEventListener('click', function () {
            startEditing(card, comment, body, actions);
        });

        deleteButton.addEventListener('click', function () {
            deleteComment(card, comment, deleteButton);
        });

        actions.append(editButton, deleteButton);
        card.append(actions);
    }

    return card;
}

// Turns the comment's text into an editable box with Save and Cancel buttons.
function startEditing(card, comment, body, actions) {
    actions.hidden = true;   // hide Edit/Delete while editing

    const editBox = makeElement('div', 'edit-box');

    const textarea = makeElement('textarea', 'edit-textarea');
    textarea.rows = 3;
    textarea.maxLength = MAX_COMMENT_LENGTH;
    textarea.value = comment.content || '';
    textarea.setAttribute('aria-label', 'Edit your comment');

    const editError = makeElement('p', 'edit-error');
    editError.hidden = true;

    const buttons = makeElement('div', 'edit-actions');
    const saveButton = makeButton('button-small', 'Save');
    const cancelButton = makeButton('button-small button-secondary', 'Cancel');

    // Cancel: redraw the card from the original data.
    cancelButton.addEventListener('click', function () {
        card.replaceWith(createCommentCard(comment));
    });

    // Save: send the new text to the server.
    saveButton.addEventListener('click', async function () {
        const newText = textarea.value.trim();

        // A friendly check. The server checks again.
        if (newText === '' && !comment.image_path) {
            editError.textContent = 'A comment cannot be empty. Use Delete to remove it.';
            editError.hidden = false;
            return;
        }

        saveButton.disabled = true;
        cancelButton.disabled = true;
        saveButton.textContent = 'Saving...';
        editError.hidden = true;

        try {
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('comment_id', comment.id);
            formData.append('content', newText);

            const response = await fetch('../php/api/edit_comment.php', {
                method: 'POST',
                body: formData
            });

            const data = await readJson(response);

            if (data.success) {
                // Replace the old card with a fresh one built from the updated comment.
                card.replaceWith(createCommentCard(data.comment));
                return;
            }

            editError.textContent = data.message;
            editError.hidden = false;
        } catch (error) {
            editError.textContent = errorText(error);
            editError.hidden = false;
        }

        saveButton.disabled = false;
        cancelButton.disabled = false;
        saveButton.textContent = 'Save';
    });

    buttons.append(saveButton, cancelButton);
    editBox.append(textarea, editError, buttons);

    body.replaceChildren(editBox);
    textarea.focus();
}

// Asks for confirmation, then deletes the comment.
async function deleteComment(card, comment, deleteButton) {
    if (!window.confirm('Delete this comment? This cannot be undone.')) {
        return;
    }

    clearMessage();
    deleteButton.disabled = true;

    try {
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('comment_id', comment.id);

        const response = await fetch('../php/api/delete_comment.php', {
            method: 'POST',
            body: formData
        });

        const data = await readJson(response);

        // A 404 means the comment is already gone, so removing the card is correct too.
        if (data.success || response.status === 404) {
            card.remove();

            if (commentList.children.length === 0) {
                emptyState.hidden = false;
            }

            return;
        }

        showMessage(data.message, 'error');
        window.scrollTo({ top: 0, behavior: 'smooth' });   // so the message is visible
    } catch (error) {
        showMessage(errorText(error), 'error');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    deleteButton.disabled = false;
}

// Hides the message box.
function clearMessage() {
    const messageBox = document.getElementById('message');

    messageBox.textContent = '';
    messageBox.className = 'message';
}

// Shows a list of comments, or the "no comments" message if the list is empty.
function renderComments(comments) {
    commentList.replaceChildren();               // remove everything currently in the list

    emptyState.hidden = comments.length > 0;     // show the empty message only when needed

    for (const comment of comments) {
        commentList.append(createCommentCard(comment));
    }
}

// Asks the server for all comments and shows them.
async function loadComments() {
    emptyState.hidden = true;
    commentList.replaceChildren(makeElement('p', 'empty-state', 'Loading comments...'));

    try {
        const response = await fetch('../php/api/get_comments.php');
        const data = await readJson(response);

        if (data.success) {
            renderComments(data.comments);
        } else {
            commentList.replaceChildren();
            showMessage(data.message, 'error');
        }
    } catch (error) {
        commentList.replaceChildren();
        showMessage(errorText(error), 'error');
    }
}

// Updates the "12 / 1000" counter under the text box.
function updateCounter() {
    commentCounter.textContent = commentInput.value.length + ' / ' + MAX_COMMENT_LENGTH;
}

// Removes the chosen image and hides the preview.
function clearSelectedImage() {
    imageInput.value = '';

    if (previewUrl) {
        URL.revokeObjectURL(previewUrl);   // free the memory used by the preview
        previewUrl = null;
    }

    imagePreviewImg.removeAttribute('src');
    imagePreview.hidden = true;
}

// Shows a preview of the chosen image (nothing is uploaded yet).
function showImagePreview(file) {
    if (previewUrl) {
        URL.revokeObjectURL(previewUrl);
    }

    previewUrl = URL.createObjectURL(file);
    imagePreviewImg.src = previewUrl;
    imagePreview.hidden = false;
}

// app.js calls this function after it has loaded the logged-in user.
function onUserLoaded(user) {
    composerAvatar.src = avatarUrl(user.profile_picture);
    loadComments();
}

// Update the counter every time the user types.
commentInput.addEventListener('input', updateCounter);

// Runs when the user chooses a file.
imageInput.addEventListener('change', function () {
    clearMessage();

    const file = imageInput.files[0];

    if (!file) {                       // the file dialog was cancelled
        clearSelectedImage();
        return;
    }

    // Friendly checks. The server checks again, because these can be bypassed.
    if (!ALLOWED_IMAGE_TYPES.includes(file.type)) {
        showMessage('Only JPG, PNG, and WebP images are allowed.', 'error');
        clearSelectedImage();
        return;
    }

    if (file.size > MAX_IMAGE_SIZE) {
        showMessage('The image is too large. Maximum size is 5 MB.', 'error');
        clearSelectedImage();
        return;
    }

    showImagePreview(file);
});

// Runs when the user clicks the × on the preview.
removeImageButton.addEventListener('click', clearSelectedImage);

// Runs when the user clicks "Post".
commentForm.addEventListener('submit', async function (event) {
    event.preventDefault();   // stop the browser from reloading the page
    clearMessage();

    const text = commentInput.value.trim();
    const image = imageInput.files[0];

    if (text === '' && !image) {
        showMessage('Please write something or choose an image.', 'error');
        return;
    }

    postButton.disabled = true;
    postButton.textContent = 'Posting...';

    try {
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('content', text);

        if (image) {
            formData.append('image', image);
        }

        const response = await fetch('../php/api/add_comment.php', {
            method: 'POST',
            body: formData
        });

        const data = await readJson(response);

        if (data.success) {
            // Add the new comment at the end of the list (oldest first).
            const card = createCommentCard(data.comment);
            commentList.append(card);
            emptyState.hidden = true;

            commentInput.value = '';
            updateCounter();
            clearSelectedImage();

            card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            showMessage(data.message, 'error');
        }
    } catch (error) {
        showMessage(errorText(error), 'error');
    }

    postButton.disabled = false;
    postButton.textContent = 'Post';
});