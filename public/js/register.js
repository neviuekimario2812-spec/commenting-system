// Handles the registration form in the browser.

// While learning, show detailed problems on the page.
// Set to false when the site goes live.
const DEBUG = true;

const form = document.getElementById('register-form');
const messageBox = document.getElementById('message');
const csrfInput = document.getElementById('csrf_token');
const submitButton = document.getElementById('submit-button');

// Shows a message above the form. type is 'success' or 'error'.
function showMessage(text, type) {
    messageBox.textContent = text;
    messageBox.className = 'message ' + type;
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

// Asks PHP for a CSRF token and puts it in the hidden form field.
async function loadCsrfToken() {
    try {
        const response = await fetch('../php/api/csrf.php');
        const data = await readJson(response);
        csrfInput.value = data.csrf_token;
    } catch (error) {
        if (DEBUG) {
            showMessage('Could not prepare the form. Details: ' + error.message, 'error');
        } else {
            showMessage('Could not prepare the form. Please refresh the page.', 'error');
        }
    }
}

// Runs when the user clicks "Create account".
form.addEventListener('submit', async function (event) {
    event.preventDefault();               // stop the browser's normal form submission

    submitButton.disabled = true;         // prevent double-clicking
    submitButton.textContent = 'Creating account...';

    try {
        const response = await fetch('../php/api/register.php', {
            method: 'POST',
            body: new FormData(form)      // collects all form fields
        });

        const data = await readJson(response);

        if (data.success) {
            showMessage(data.message, 'success');
            form.reset();
        } else {
            showMessage(data.message, 'error');
        }
    } catch (error) {
        if (DEBUG) {
            showMessage('Problem: ' + error.message, 'error');
        } else {
            showMessage('Something went wrong. Please try again later.', 'error');
        }
    }

    submitButton.disabled = false;
    submitButton.textContent = 'Create account';
});

// Get the token as soon as the page opens.
loadCsrfToken();