// Behaviour that belongs only to the profile page.
// (The header, login check and logout button are handled by app.js.)

const MAX_AVATAR_SIZE = 2 * 1024 * 1024;   // 2 MB

const profileAvatar = document.getElementById('profile-avatar');
const profileEmail = document.getElementById('profile-email');

const avatarForm = document.getElementById('avatar-form');
const avatarInput = document.getElementById('avatar');
const avatarButton = document.getElementById('avatar-button');

const nameForm = document.getElementById('name-form');
const nameInput = document.getElementById('display_name');
const nameButton = document.getElementById('name-button');

// app.js calls this function after it has loaded the logged-in user.
function onUserLoaded(user) {
    profileAvatar.src = avatarUrl(user.profile_picture);
    nameInput.value = user.display_name;
    profileEmail.textContent = user.email;
}

// Show a preview as soon as the user chooses a file (nothing is uploaded yet).
avatarInput.addEventListener('change', function () {
    const file = avatarInput.files[0];

    if (file) {
        profileAvatar.src = URL.createObjectURL(file);
    } else if (currentUser) {
        profileAvatar.src = avatarUrl(currentUser.profile_picture);
    }
});

// Upload the chosen picture.
avatarForm.addEventListener('submit', async function (event) {
    event.preventDefault();

    const file = avatarInput.files[0];

    if (!file) {
        showMessage('Please choose a picture first.', 'error');
        return;
    }

    // A quick friendly check. The server checks again, because this one can be bypassed.
    if (file.size > MAX_AVATAR_SIZE) {
        showMessage('The picture is too large. Maximum size is 2 MB.', 'error');
        return;
    }

    avatarButton.disabled = true;
    avatarButton.textContent = 'Uploading...';

    try {
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('avatar', file);

        const response = await fetch('../php/api/upload_avatar.php', {
            method: 'POST',
            body: formData
        });

        const data = await readJson(response);

        if (data.success) {
            currentUser.profile_picture = data.profile_picture;
            updateHeader();
            profileAvatar.src = avatarUrl(data.profile_picture);
            avatarForm.reset();
            showMessage(data.message, 'success');
        } else {
            profileAvatar.src = avatarUrl(currentUser.profile_picture);   // undo the preview
            showMessage(data.message, 'error');
        }
    } catch (error) {
        showMessage(errorText(error), 'error');
    }

    avatarButton.disabled = false;
    avatarButton.textContent = 'Upload picture';
});

// Save the new display name.
nameForm.addEventListener('submit', async function (event) {
    event.preventDefault();

    nameButton.disabled = true;
    nameButton.textContent = 'Saving...';

    try {
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('display_name', nameInput.value);

        const response = await fetch('../php/api/update_profile.php', {
            method: 'POST',
            body: formData
        });

        const data = await readJson(response);

        if (data.success) {
            currentUser.display_name = data.display_name;
            nameInput.value = data.display_name;
            updateHeader();
            showMessage(data.message, 'success');
        } else {
            showMessage(data.message, 'error');
        }
    } catch (error) {
        showMessage(errorText(error), 'error');
    }

    nameButton.disabled = false;
    nameButton.textContent = 'Save name';
});