<?php

/*
 * This file demonstrates the modern, secure way to hash and verify
 * passwords in PHP using password_hash() and password_verify().
 *
 * --- DO NOT USE md5() or sha1() FOR PASSWORDS. ---
 * They are outdated, "fast" (which is bad for passwords), and vulnerable.
 */

// Set header for clean browser output
header('Content-Type: text/plain');

// --- 1. HASHING A PASSWORD (What you do during user registration) ---

echo "--- 1. Hashing a Password (for Registration) ---\n\n";

// The plain-text password from a registration form
$plainTextPassword = 'superadmin@0330';

/*
 * password_hash() - The correct function to use.
 *
 * Argument 1: The plain-text password.
 * Argument 2: The algorithm. PASSWORD_DEFAULT is the best choice.
 * It currently defaults to 'bcrypt' and will be updated
 * by PHP in the future if a stronger algorithm becomes standard.
 *
 * This function automatically handles:
 * - A strong, slow hashing algorithm (like bcrypt)
 * - Generating a unique, random "salt" for every hash
 * - Combining the salt and hash into a single string for storage.
 */
$hashedPassword = password_hash($plainTextPassword, PASSWORD_DEFAULT);

echo "Plain Text:  " . $plainTextPassword . "\n";
echo "Hashed (Store this in your database):\n" . $hashedPassword . "\n";
echo "--------------------------------------------------\n\n";


// --- 2. VERIFYING A PASSWORD (What you do during user login) ---

echo "--- 2. Verifying a Password (for Login) ---\n\n";

// A. The user tries to log in with the correct password
$loginAttempt_Correct = 'MySuperSecretPassword123';

echo "Attempting to log in with: '" . $loginAttempt_Correct . "'\n";

/*
 * password_verify() - The correct function to check a hash.
 *
 * It securely compares the plain-text attempt against the stored hash.
 * It automatically reads the salt and algorithm info from the $hashedPassword string.
 *
 * Argument 1: The plain-text password from the login form.
 * Argument 2: The full hash string you stored in your database.
 */
if (password_verify($loginAttempt_Correct, $hashedPassword)) {
    echo "Result: SUCCESS! The password is correct.\n\n";
} else {
    echo "Result: FAILED. Invalid password.\n\n";
}


// B. The user tries to log in with the wrong password
$loginAttempt_Wrong = 'WrongPassword!';

echo "Attempting to log in with: '" . $loginAttempt_Wrong . "'\n";

if (password_verify($loginAttempt_Wrong, $hashedPassword)) {
    echo "Result: SUCCESS! The password is correct.\n\n";
} else {
    echo "Result: FAILED. Invalid password. (This is the correct outcome)\n\n";
}

echo "--------------------------------------------------\n\n";
echo "Note: The hash will be different every time you run this file,\n";
echo "but password_verify() will still work. That's the salt in action!";

?>