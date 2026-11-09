DataReadyTest.online — Server-backed assessment

WHAT IS INCLUDED
- index.html: public assessment with a required storage-consent checkbox.
- admin.php: password-protected page for viewing and deleting completed reports.
- api/submit.php: validates and stores a completed assessment.
- privacy.html: participant privacy notice.
- setup-admin.php: CLI-only initial admin credential setup.
- includes/: shared server-side security and storage helpers.

REQUIREMENTS
- PHP 8.1 or newer, with JSON support.
- HTTPS enabled for the live site.
- Hosting that runs PHP scripts. GitHub Pages is static hosting and cannot run this backend; this app will not store results on GitHub Pages.
- The default private data directory is created next to, but outside, the public site directory. The PHP process must be allowed to write there. If needed, set APP_DATA_DIR to a private writable directory outside the document root before running PHP.

INSTALL
1. Use a PHP-enabled web host and point its document root at the contents of this folder. Keep HTTPS enabled.
2. Upload all files and folders, including the hidden .htaccess file. Do not put private data inside the public web root.
3. Run setup-admin.php once from the command line in this folder: `php setup-admin.php`. The default username is dataadmin. Choose a unique password of at least 12 characters when prompted; input is hidden and the password is stored as a one-way password hash in private storage outside the web root. The credentials are not embedded in website JavaScript or this package.
4. Open `https://YOUR-DOMAIN/admin.php` and sign in.
5. Test one completion using an email address you control; confirm it appears in the admin page and delete the test report afterward.

RESULTS AND CONSENT
A participant must check the consent box before beginning. On completion, the report is sent to the server. It is stored privately and is visible to the admin login. Records remain until the administrator deletes them. The admin page offers per-report deletion. The website does not send results by email.

SECURITY NOTES
- Never host this PHP app on static-only hosting or with HTTPS disabled.
- Do not upload private_data, admin.json, or results.json into the public site folder or a public Git repository.
- Use a unique, strong administrator password and restrict who can access the admin account.
- The server protects admin sign-in with hashed credentials, session cookies, CSRF checks, output escaping, and a limit on repeated failed sign-ins. Backups of private data remain the site owner's responsibility.
- If an advertising provider is activated, update privacy.html to disclose the provider and any cookies or consent requirements before placing its code.

DEVELOPMENT PREVIEW
Open index.html?dev=1 to show the preview skip buttons. Preview-mode results are deliberately not saved to the admin database.
