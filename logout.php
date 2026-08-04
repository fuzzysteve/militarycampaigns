<?php
// Only clears this app's own mc_* session keys - never session_unset()/
// session_destroy(), which would also wipe the unrelated sitewide /auth/
// login (auth_characterid etc.) if a visitor happens to be logged into both.
session_start();
unset($_SESSION['mc_character_id']);
unset($_SESSION['mc_character_name']);
unset($_SESSION['mc_activity_data']);
unset($_SESSION['mc_activity_fetched_at']);
unset($_SESSION['mc_auth_state']);
unset($_SESSION['mc_auth_redirect']);
session_write_close();

header('Location: https://www.fuzzwork.co.uk/militarycampaigns/');
exit;
