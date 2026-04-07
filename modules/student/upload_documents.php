<?php
require_once '../../config/init.php';
require_login();
require_role(ROLE_STUDENT);

// Backward-compatible route: old upload_documents now maps to regulation form.
redirect('modules/student/submit_documents.php');
