document.addEventListener('DOMContentLoaded', function () {
    var logoutButtons = document.querySelectorAll('.logout-btn');

    logoutButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var shouldLogout = window.confirm('Voulez-vous vraiment vous deconnecter ?');

            if (!shouldLogout) {
                return;
            }

            sessionStorage.removeItem('educlassLoggedIn');
            window.location.replace('../index.html');
        });
    });
});
