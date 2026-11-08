// Behaviour that belongs only to the home page.

// app.js calls this function after it has loaded the logged-in user.
function onUserLoaded(user) {
    document.getElementById('welcome-title').textContent = 'Welcome, ' + user.display_name + '!';
    document.getElementById('welcome-text').textContent =
        'You are logged in. The comment area will appear here in a later step.';
}