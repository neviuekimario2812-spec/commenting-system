// Shared code for every page that needs a logged-in user.
// It checks the login, fills in the header, and handles logging out.
// A page can define its own function named onUserLoaded(user);
// this file will call it once the user's details are known.

// While learning, show detailed problems on the page.
// Set to false when the site goes live.
const DEBUG = true;

const DEFAULT_AVATAR = 'images/default-avatar.svg';

let csrfToken = '';       // secret token sent with every form submission
let currentUser = null;   // details of the logged-in user

const headerName = document.getElementById('header-name');
const headerAvatar = document.getElementById('header-avatar');
const logoutButton = document.getElementById('logout-button');

// Shows a message in the element with id="message". type is 'success' or 'error'.
function showMessage(text, type) {
    const messageBox = document.getElementById('message');

    if (!messageBox) {
        return;
    }

    messageBox.textContent = text;
    messageBox.className = 'message ' + type;
}

// Returns the picture to display: the user's own, or the default one.
function avatarUrl(path) {
    return path ? path : DEFAULT_AVATAR;
}

// Reads a server reply as text, then converts it to JSON.
// If the reply is not valid JSON, it throws an error that includes what the server sent.
async function readJson(response) {
    const rawText = await response.text();

    try {
        return JSON.parse(rawText);
    } catch (parseError) {
        console.error('Server did not return JSON. It returned:', rawText);
        throw new Error('HTTP ' + response.status + ': ' + rawText.substring(0, 300));
    }
}

// Turns an error into text for the user.
function errorText(error) {
    if (DEBUG) {
        return 'Problem: ' + error.message;
    }

    return 'Something went wrong. Please try again later.';
}

// Puts the user's name and picture in the page header.
function updateHeader() {
    headerName.textContent = currentUser.display_name;
    headerAvatar.src = avatarUrl(currentUser.profile_picture);
}

// Asks PHP who is logged in. If nobody is, go to the login page.
async function loadCurrentUser() {
    try {
        const response = await fetch('../php/api/me.php');

        if (response.status === 401) {
            window.location.href = 'login.html';
            return;
        }

        const data = await readJson(response);

        csrfToken = data.csrf_token;
        currentUser = data.user;
        updateHeader();

        // Let the page do its own work now that the user is known.
        if (typeof onUserLoaded === 'function') {
            onUserLoaded(currentUser);
        }
    } catch (error) {
        showMessage(errorText(error), 'error');
    }
}

// Runs when the user clicks "Log out".
logoutButton.addEventListener('click', async function () {
    logoutButton.disabled = true;

    try {
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);

        const response = await fetch('../php/api/logout.php', {
            method: 'POST',
            body: formData
        });

        const data = await readJson(response);

        if (data.success) {
            window.location.href = 'login.html';
            return;
        }

        showMessage(data.message, 'error');
    } catch (error) {
        showMessage(errorText(error), 'error');
    }

    logoutButton.disabled = false;
});

// Check the login status as soon as the page opens.
loadCurrentUser();