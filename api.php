<?php
// Set error reporting to display JSON errors
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database file for users and workouts
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

$usersFile = $dataDir . '/users.json';
$workoutsFile = $dataDir . '/workouts.json';
$challengesFile = $dataDir . '/challenges.json';
$ratingsFile = $dataDir . '/ratings.json';
$exerciseActivitiesFile = $dataDir . '/exercise-activities.json';
$friendsFile = $dataDir . '/friends.json';

// XP Multipliers for different exercises
$XP_MULTIPLIERS = [
    'push-ups' => 1.0,
    'pull-ups' => 1.5,
    'sit-ups' => 0.25
];

// Initialize files if they don't exist
if (!file_exists($usersFile)) {
    file_put_contents($usersFile, json_encode([]));
}
if (!file_exists($workoutsFile)) {
    file_put_contents($workoutsFile, json_encode([]));
}
if (!file_exists($challengesFile)) {
    file_put_contents($challengesFile, json_encode([]));
}
if (!file_exists($ratingsFile)) {
    file_put_contents($ratingsFile, json_encode([]));
}
if (!file_exists($exerciseActivitiesFile)) {
    file_put_contents($exerciseActivitiesFile, json_encode([]));
}
if (!file_exists($friendsFile)) {
    file_put_contents($friendsFile, json_encode([]));
}

// Get request method and action
$action = $_GET['action'] ?? $_POST['action'] ?? null;

// If action is null, try to parse from raw input (FormData sometimes needs this)
if (!$action) {
    $input = file_get_contents("php://input");
    parse_str($input, $parsedInput);
    $action = $parsedInput['action'] ?? null;
    // Also merge parsed input into $_POST for later use
    $_POST = array_merge($_POST, $parsedInput);
}

// Helper functions
function getUsers() {
    global $usersFile;
    return json_decode(file_get_contents($usersFile), true) ?: [];
}

function saveUsers($users) {
    global $usersFile;
    file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
}

function getWorkouts() {
    global $workoutsFile;
    return json_decode(file_get_contents($workoutsFile), true) ?: [];
}

function saveWorkouts($workouts) {
    global $workoutsFile;
    file_put_contents($workoutsFile, json_encode($workouts, JSON_PRETTY_PRINT));
}

function getChallenges() {
    global $challengesFile;
    return json_decode(file_get_contents($challengesFile), true) ?: [];
}

function saveChallenges($challenges) {
    global $challengesFile;
    file_put_contents($challengesFile, json_encode($challenges, JSON_PRETTY_PRINT));
}

function getRatings() {
    global $ratingsFile;
    return json_decode(file_get_contents($ratingsFile), true) ?: [];
}

function saveRatings($ratings) {
    global $ratingsFile;
    file_put_contents($ratingsFile, json_encode($ratings, JSON_PRETTY_PRINT));
}

function getExerciseActivities() {
    global $exerciseActivitiesFile;
    return json_decode(file_get_contents($exerciseActivitiesFile), true) ?: [];
}

function saveExerciseActivities($activities) {
    global $exerciseActivitiesFile;
    file_put_contents($exerciseActivitiesFile, json_encode($activities, JSON_PRETTY_PRINT));
}

function getFriends() {
    global $friendsFile;
    return json_decode(file_get_contents($friendsFile), true) ?: [];
}

function saveFriends($friends) {
    global $friendsFile;
    file_put_contents($friendsFile, json_encode($friends, JSON_PRETTY_PRINT));
}

function calculateXP($exerciseName, $reps) {
    global $XP_MULTIPLIERS;
    $baseXP = $reps; // Base XP is 1 per rep for push-ups
    $multiplier = $XP_MULTIPLIERS[$exerciseName] ?? 1.0;
    return $baseXP * $multiplier;
}

function respond($success, $message = '', $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

function sendEmailNotification($to, $subject, $body) {
    $headers = "From: pushpal@notifications.com\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    return mail($to, $subject, $body, $headers);
}

// Wrap everything in try-catch for error handling
try {
    // Actions
    switch ($action) {
    // SIGNUP
    case 'signup':
        $username = $_POST['username'] ?? null;
        $password = $_POST['password'] ?? null;
        
        if (!$username || !$password) {
            respond(false, 'Username and password required');
        }
        
        $users = getUsers();
        if (isset($users[$username])) {
            respond(false, 'Username already registered');
        }
        
        // Generate unique user ID (6 digit alphanumeric)
        $userId = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        
        $token = bin2hex(random_bytes(32));
        $users[$username] = [
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'token' => $token,
            'userId' => $userId,
            'createdAt' => date('Y-m-d H:i:s'),
            'lastLogin' => date('Y-m-d H:i:s'),
            'questionary_completed' => false,
            'minimum_goal' => 0,
            'optimal_goal' => 0,
            'last_rating_week' => 0,
            'profile' => [
                'name' => '',
                'age' => '',
                'weight' => ''
            ],
            'xp' => [
                'push-ups' => 0,
                'pull-ups' => 0,
                'sit-ups' => 0
            ],
            'total_xp' => 0
        ];
        
        saveUsers($users);
        respond(true, 'Account created successfully', [
            'username' => $username,
            'userId' => $userId,
            'profile' => $users[$username]['profile'],
            'questionary_completed' => false
        ]);
        break;
    
    // LOGIN
    case 'login':
        $username = $_POST['username'] ?? null;
        $password = $_POST['password'] ?? null;
        
        if (!$username || !$password) {
            respond(false, 'Username and password required');
        }
        
        $users = getUsers();
        if (!isset($users[$username]) || !password_verify($password, $users[$username]['password'])) {
            respond(false, 'Invalid username or password');
        }
        
        // Create session token
        $token = bin2hex(random_bytes(32));
        $users[$username]['token'] = $token;
        $users[$username]['lastLogin'] = date('Y-m-d H:i:s');
        
        // Auto-generate userId for existing accounts without one
        if (empty($users[$username]['userId'])) {
            $users[$username]['userId'] = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        }
        
        $currentWeek = (int)date('W');
        $lastRatingWeek = $users[$username]['last_rating_week'] ?? 0;
        $shouldShowRating = ($currentWeek > 1) && ($currentWeek !== $lastRatingWeek);
        
        saveUsers($users);
        
        respond(true, 'Login successful', [
            'username' => $username,
            'token' => $token,
            'userId' => $users[$username]['userId'] ?? '',
            'profile' => $users[$username]['profile'],
            'questionary_completed' => $users[$username]['questionary_completed'] ?? false,
            'minimum_goal' => $users[$username]['minimum_goal'] ?? 0,
            'optimal_goal' => $users[$username]['optimal_goal'] ?? 0,
            'should_show_rating' => $shouldShowRating
        ]);
        break;
    
    // SET QUESTIONNAIRE ANSWERS
    case 'setQuestionnaireAnswers':
        $username = $_POST['username'] ?? null;
        $token = $_POST['token'] ?? null;
        $minimum_goal = $_POST['minimum_goal'] ?? null;
        $optimal_goal = $_POST['optimal_goal'] ?? null;
        
        if (!$username || !$token || $minimum_goal === null || $optimal_goal === null) {
            respond(false, 'Missing required parameters');
        }
        
        $users = getUsers();
        if (!isset($users[$username]) || $users[$username]['token'] !== $token) {
            respond(false, 'Unauthorized');
        }
        
        $users[$username]['questionary_completed'] = true;
        $users[$username]['minimum_goal'] = (int)$minimum_goal;
        $users[$username]['optimal_goal'] = (int)$optimal_goal;
        saveUsers($users);
        
        respond(true, 'Questionnaire saved', [
            'minimum_goal' => $minimum_goal,
            'optimal_goal' => $optimal_goal
        ]);
        break;
    
    // GET WORKOUTS
    case 'getWorkouts':
        $username = $_POST['username'] ?? null;
        $token = $_POST['token'] ?? null;
        
        if (!$username || !$token) {
            respond(false, 'Username and token required');
        }
        
        $users = getUsers();
        if (!isset($users[$username]) || $users[$username]['token'] !== $token) {
            respond(false, 'Unauthorized');
        }
        
        $workouts = getWorkouts();
        $userWorkouts = $workouts[$username] ?? [];
        
        respond(true, 'Workouts retrieved', ['workouts' => $userWorkouts]);
        break;
    
    // SAVE WORKOUTS
    case 'saveWorkouts':
        $username = $_POST['username'] ?? null;
        $token = $_POST['token'] ?? null;
        $workouts = $_POST['workouts'] ?? null;
        
        if (!$username || !$token || !$workouts) {
            respond(false, 'Username, token, and workouts required');
        }
        
        $users = getUsers();
        if (!isset($users[$username]) || $users[$username]['token'] !== $token) {
            respond(false, 'Unauthorized');
        }
        
        $workoutsData = getWorkouts();
        $workoutsData[$username] = json_decode($workouts, true);
        saveWorkouts($workoutsData);
        
        respond(true, 'Workouts saved', ['workouts' => $workoutsData[$username]]);
        break;
    
    // CREATE CHALLENGE
    case 'createChallenge':
        $username = $_POST['username'] ?? null;
        $token = $_POST['token'] ?? null;
        $name = $_POST['name'] ?? null;
        $description = $_POST['description'] ?? null;
        $reps = $_POST['reps'] ?? null;
        $time_minutes = $_POST['time_minutes'] ?? null;
        
        if (!$username || !$token || !$name || !$reps || !$time_minutes) {
            respond(false, 'Missing required fields');
        }
        
        $users = getUsers();
        if (!isset($users[$username]) || $users[$username]['token'] !== $token) {
            respond(false, 'Unauthorized');
        }
        
        $challenges = getChallenges();
        if (!isset($challenges[$username])) {
            $challenges[$username] = [];
        }
        
        $newChallenge = [
            'id' => bin2hex(random_bytes(8)),
            'name' => $name,
            'description' => $description,
            'reps' => (int)$reps,
            'time_minutes' => (int)$time_minutes,
            'created_at' => date('Y-m-d H:i:s'),
            'completed' => false,
            'current_reps' => 0
        ];
        
        $challenges[$username][] = $newChallenge;
        saveChallenges($challenges);
        
        respond(true, 'Challenge created', ['challenge' => $newChallenge]);
        break;
    
    // GET CHALLENGES
    case 'getChallenges':
        $username = $_POST['username'] ?? null;
        $token = $_POST['token'] ?? null;
        
        if (!$username || !$token) {
            respond(false, 'Username and token required');
        }
        
        $users = getUsers();
        if (!isset($users[$username]) || $users[$username]['token'] !== $token) {
            respond(false, 'Unauthorized');
        }
        
        $challenges = getChallenges();
        $userChallenges = $challenges[$username] ?? [];
        
        respond(true, 'Challenges retrieved', ['challenges' => $userChallenges]);
        break;
    
    // SAVE RATING
    case 'saveRating':
        $username = $_POST['username'] ?? null;
        $token = $_POST['token'] ?? null;
        $stars = $_POST['stars'] ?? null;
        
        if (!$username || !$token || !$stars) {
            respond(false, 'Missing required fields');
        }
        
        $users = getUsers();
        if (!isset($users[$username]) || $users[$username]['token'] !== $token) {
            respond(false, 'Unauthorized');
        }
        
        $currentWeek = (int)date('W');
        $users[$username]['last_rating_week'] = $currentWeek;
        saveUsers($users);
        
        $ratings = getRatings();
        if (!isset($ratings[$username])) {
            $ratings[$username] = [];
        }
        
        $rating = [
            'date' => date('Y-m-d H:i:s'),
            'week' => $currentWeek,
            'stars' => (int)$stars
        ];
        
        $ratings[$username][] = $rating;
        saveRatings($ratings);
        
        // Send email notification
        $subject = "Weekly Rating from $username";
        $body = "User $username rated their week with $stars stars on " . date('Y-m-d H:i:s');
        sendEmailNotification('firemarkpro@gmail.com', $subject, $body);
        
        respond(true, 'Rating saved and email sent', ['rating' => $rating]);
        break;
    
    // GET PROFILE
    case 'getProfile':
        $username = $_POST['username'] ?? null;
        $token = $_POST['token'] ?? null;
        
        if (!$username || !$token) {
            respond(false, 'Username and token required');
        }
        
        $users = getUsers();
        if (!isset($users[$username]) || $users[$username]['token'] !== $token) {
            respond(false, 'Unauthorized');
        }
        
        // Auto-generate userId for existing accounts without one
        if (empty($users[$username]['userId'])) {
            $users[$username]['userId'] = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            saveUsers($users);
        }
        
        respond(true, 'Profile retrieved', [
            'profile' => $users[$username]['profile'],
            'userId' => $users[$username]['userId'] ?? '',
            'minimum_goal' => $users[$username]['minimum_goal'] ?? 0,
            'optimal_goal' => $users[$username]['optimal_goal'] ?? 0,
            'createdAt' => $users[$username]['createdAt']
        ]);
        break;
    
    // UPDATE PROFILE
    case 'updateProfile':
        $username = $_POST['username'] ?? null;
        $token = $_POST['token'] ?? null;
        $profile = $_POST['profile'] ?? null;
        $minimum_goal = $_POST['minimum_goal'] ?? null;
        $optimal_goal = $_POST['optimal_goal'] ?? null;
        
        if (!$username || !$token) {
            respond(false, 'Username and token required');
        }
        
        $users = getUsers();
        if (!isset($users[$username]) || $users[$username]['token'] !== $token) {
            respond(false, 'Unauthorized');
        }
        
        if ($profile) {
            $users[$username]['profile'] = json_decode($profile, true);
        }
        
        if ($minimum_goal !== null) {
            $users[$username]['minimum_goal'] = (int)$minimum_goal;
        }
        
        if ($optimal_goal !== null) {
            $users[$username]['optimal_goal'] = (int)$optimal_goal;
        }
        
        saveUsers($users);
        
        respond(true, 'Profile updated', [
            'profile' => $users[$username]['profile'],
            'minimum_goal' => $users[$username]['minimum_goal'],
            'optimal_goal' => $users[$username]['optimal_goal']
        ]);
        break;
    
    // LOGOUT
    case 'logout':
        $username = $_POST['username'] ?? null;
        $users = getUsers();
        
        if (isset($users[$username])) {
            unset($users[$username]['token']);
            saveUsers($users);
        }
        
        respond(true, 'Logged out');
        break;

    // VERIFY PASSWORD
    case 'verifyPassword':
        $username = $_POST['username'] ?? null;
        $password = $_POST['password'] ?? null;
        $token = $_POST['token'] ?? null;

        if (!$username || !$password || !$token) {
            respond(false, 'Missing required parameters');
        }

        $users = getUsers();
        if (!isset($users[$username]) || $users[$username]['token'] !== $token) {
            respond(false, 'Invalid session');
        }

        if (!password_verify($password, $users[$username]['password'])) {
            respond(false, 'Invalid password');
        }

        respond(true, 'Password verified');
        break;

    // RESET ALL PROGRESS
    case 'resetProgress':
        $username = $_POST['username'] ?? null;
        $token = $_POST['token'] ?? null;

        if (!$username || !$token) {
            respond(false, 'Missing required parameters');
        }

        $users = getUsers();
        if (!isset($users[$username]) || $users[$username]['token'] !== $token) {
            respond(false, 'Invalid session');
        }

        // Reset all workout data
        $workouts = getWorkouts();
        if (isset($workouts[$username])) {
            $workouts[$username] = [];
        }
        saveWorkouts($workouts);

        // Reset user XP and goals
        $users[$username]['xp'] = ['push-ups' => 0, 'pull-ups' => 0, 'sit-ups' => 0];
        $users[$username]['total_xp'] = 0;
        $users[$username]['minimum_goal'] = 0;
        $users[$username]['optimal_goal'] = 0;
        $users[$username]['last_rating_week'] = 0;
        $users[$username]['questionary_completed'] = false;
        saveUsers($users);

        respond(true, 'All progress reset successfully');
        break;
    
    // ═══════════════════════════════════════════════════════════════
    // MULTI-EXERCISE ACTIVITY TRACKING
    // ═══════════════════════════════════════════════════════════════
    
    // SAVE EXERCISE ACTIVITY (with XP calculation)
    case 'saveExerciseActivity':
        $username = $_POST['username'] ?? null;
        $token = $_POST['token'] ?? null;
        $exerciseName = $_POST['exercise_name'] ?? null;
        $reps = $_POST['reps'] ?? null;
        
        if (!$username || !$token || !$exerciseName || !$reps) {
            respond(false, 'Missing required parameters');
        }
        
        $users = getUsers();
        if (!isset($users[$username]) || $users[$username]['token'] !== $token) {
            respond(false, 'Unauthorized');
        }
        
        $activities = getExerciseActivities();
        if (!isset($activities[$username])) {
            $activities[$username] = [];
        }
        
        $xpEarned = calculateXP($exerciseName, (int)$reps);
        
        // Initialize user XP if not exists
        if (!isset($users[$username]['xp'])) {
            $users[$username]['xp'] = [
                'push-ups' => 0,
                'pull-ups' => 0,
                'sit-ups' => 0
            ];
        }
        if (!isset($users[$username]['total_xp'])) {
            $users[$username]['total_xp'] = 0;
        }
        
        // Update user XP
        if (!isset($users[$username]['xp'][$exerciseName])) {
            $users[$username]['xp'][$exerciseName] = 0;
        }
        $users[$username]['xp'][$exerciseName] += $xpEarned;
        $users[$username]['total_xp'] += $xpEarned;
        saveUsers($users);
        
        $activity = [
            'id' => bin2hex(random_bytes(8)),
            'exercise' => $exerciseName,
            'reps' => (int)$reps,
            'xp_earned' => $xpEarned,
            'date' => date('Y-m-d H:i:s')
        ];
        
        $activities[$username][] = $activity;
        saveExerciseActivities($activities);
        
        respond(true, 'Exercise activity saved', [
            'activity' => $activity,
            'xp_earned' => $xpEarned,
            'total_xp' => $users[$username]['total_xp']
        ]);
        break;
    
    // GET EXERCISE ACTIVITIES FOR SPECIFIC EXERCISE TYPE
    case 'getExerciseActivities':
        $username = $_POST['username'] ?? null;
        $token = $_POST['token'] ?? null;
        $exerciseName = $_POST['exercise_name'] ?? null; // Optional filter
        
        if (!$username || !$token) {
            respond(false, 'Username and token required');
        }
        
        $users = getUsers();
        if (!isset($users[$username]) || $users[$username]['token'] !== $token) {
            respond(false, 'Unauthorized');
        }
        
        $activities = getExerciseActivities();
        $userActivities = $activities[$username] ?? [];
        
        // Filter by exercise type if specified
        if ($exerciseName) {
            $userActivities = array_filter($userActivities, function($activity) use ($exerciseName) {
                return $activity['exercise'] === $exerciseName;
            });
        }
        
        respond(true, 'Activities retrieved', ['activities' => array_values($userActivities)]);
        break;
    
    // GET USER STATS BY EXERCISE
    case 'getUserStatsByExercise':
        $username = $_POST['username'] ?? null;
        $token = $_POST['token'] ?? null;
        
        if (!$username || !$token) {
            respond(false, 'Username and token required');
        }
        
        $users = getUsers();
        if (!isset($users[$username]) || $users[$username]['token'] !== $token) {
            respond(false, 'Unauthorized');
        }
        
        $activities = getExerciseActivities();
        $userActivities = $activities[$username] ?? [];
        
        $stats = [];
        $exerciseTypes = ['push-ups', 'pull-ups', 'sit-ups'];
        
        foreach ($exerciseTypes as $exercise) {
            $exerciseActivities = array_filter($userActivities, function($activity) use ($exercise) {
                return $activity['exercise'] === $exercise;
            });
            
            $totalReps = 0;
            $totalXP = 0;
            $count = 0;
            foreach ($exerciseActivities as $activity) {
                $totalReps += $activity['reps'];
                $totalXP += $activity['xp_earned'];
                $count++;
            }
            
            $stats[$exercise] = [
                'total_reps' => $totalReps,
                'total_xp' => $totalXP,
                'workouts_count' => $count,
                'last_workout' => count($exerciseActivities) > 0 ? 
                    max(array_map(function($a) { return strtotime($a['date']); }, $exerciseActivities)) 
                    : null
            ];
        }
        
        respond(true, 'Stats retrieved', [
            'stats_by_exercise' => $stats,
            'total_xp' => $users[$username]['total_xp'] ?? 0,
            'xp_by_exercise' => $users[$username]['xp'] ?? []
        ]);
        break;
    
    // ═══════════════════════════════════════════════════════════════
    // FRIENDS MANAGEMENT
    // ═══════════════════════════════════════════════════════════════
    
    // SEARCH USER BY NAME OR ID
    case 'searchUser':
        $username = $_POST['username'] ?? null;
        $token = $_POST['token'] ?? null;
        $search = $_POST['search'] ?? null;
        
        if (!$username || !$token || !$search) {
            respond(false, 'Missing required parameters');
        }
        
        $users = getUsers();
        if (!isset($users[$username]) || $users[$username]['token'] !== $token) {
            respond(false, 'Unauthorized');
        }
        
        $search = trim(strtolower($search));
        $foundUser = null;
        
        // Search by username or userId
        foreach ($users as $uname => $udata) {
            $searchLower = strtolower($search);
            $usernameLower = strtolower($uname);
            $userIdLower = isset($udata['userId']) ? strtolower($udata['userId']) : '';
            
            // Match by exact username or exact userId match (not substring)
            if ($usernameLower === $searchLower || $userIdLower === $searchLower) {
                if ($uname !== $username) { // Don't return self
                    $foundUser = [
                        'username' => $uname,
                        'userId' => $udata['userId'] ?? $uname,
                        'profile' => $udata['profile'] ?? ['name' => '', 'age' => '', 'weight' => ''],
                        'total_xp' => $udata['total_xp'] ?? 0,
                        'createdAt' => $udata['createdAt'] ?? ''
                    ];
                }
                break;
            }
        }
        
        if (!$foundUser) {
            respond(false, 'User not found');
        }
        
        respond(true, 'User found', [
            'user' => $foundUser,
            'friend_status' => 'add'
        ]);
        break;
    
    case 'searchSimilar':
        $username = $_POST['username'] ?? null;
        $token = $_POST['token'] ?? null;
        $search = $_POST['search'] ?? null;
        
        if (!$username || !$token || !$search) {
            respond(false, 'Missing required parameters');
        }
        
        $users = getUsers();
        if (!isset($users[$username]) || $users[$username]['token'] !== $token) {
            respond(false, 'Unauthorized');
        }
        
        $search = trim(strtolower($search));
        $foundUsers = [];
        
        // Search for similar usernames (substring matching, not friend codes)
        foreach ($users as $uname => $udata) {
            if ($uname === $username) continue; // Skip self
            
            $usernameLower = strtolower($uname);
            $searchLower = strtolower($search);
            
            // Only search by username substring, not by userId/friend code
            if (strpos($usernameLower, $searchLower) !== false) {
                $foundUsers[] = [
                    'username' => $uname,
                    'userId' => $udata['userId'] ?? $uname,
                    'profile' => $udata['profile'] ?? ['name' => '', 'age' => '', 'weight' => ''],
                    'total_xp' => $udata['total_xp'] ?? 0,
                    'createdAt' => $udata['createdAt'] ?? ''
                ];
            }
        }
        
        respond(true, 'Similar users found', [
            'users' => $foundUsers
        ]);
        break;
    
    // SEND FRIEND REQUEST
    case 'sendFriendRequest':
        $username = $_POST['username'] ?? null;
        $token = $_POST['token'] ?? null;
        $targetUser = $_POST['target_user'] ?? null;
        
        if (!$username || !$token || !$targetUser) {
            respond(false, 'Missing required parameters');
        }
        
        $users = getUsers();
        if (!isset($users[$username]) || $users[$username]['token'] !== $token) {
            respond(false, 'Unauthorized');
        }
        
        if (!isset($users[$targetUser])) {
            respond(false, 'Target user not found');
        }
        
        if ($username === $targetUser) {
            respond(false, 'Cannot add yourself');
        }
        
        $friends = getFriends();
        if (!isset($friends[$username])) {
            $friends[$username] = ['friends' => [], 'requests_sent' => [], 'requests_received' => []];
        }
        if (!isset($friends[$targetUser])) {
            $friends[$targetUser] = ['friends' => [], 'requests_sent' => [], 'requests_received' => []];
        }
        
        // Check if already friends
        if (in_array($targetUser, $friends[$username]['friends'])) {
            respond(false, 'Already friends');
        }
        
        // Check if request already sent
        if (in_array($targetUser, $friends[$username]['requests_sent'])) {
            respond(false, 'Friend request already sent');
        }
        
        // Add request
        $friends[$username]['requests_sent'][] = $targetUser;
        $friends[$targetUser]['requests_received'][] = $username;
        saveFriends($friends);
        
        respond(true, 'Friend request sent');
        break;
    
    // ACCEPT FRIEND REQUEST
    case 'acceptFriendRequest':
        $username = $_POST['username'] ?? null;
        $token = $_POST['token'] ?? null;
        $fromUser = $_POST['from_user'] ?? null;
        
        if (!$username || !$token || !$fromUser) {
            respond(false, 'Missing required parameters');
        }
        
        $users = getUsers();
        if (!isset($users[$username]) || $users[$username]['token'] !== $token) {
            respond(false, 'Unauthorized');
        }
        
        $friends = getFriends();
        if (!isset($friends[$username])) {
            respond(false, 'No friend requests');
        }
        
        if (!in_array($fromUser, $friends[$username]['requests_received'])) {
            respond(false, 'No friend request from this user');
        }
        
        // Add to friends
        if (!in_array($fromUser, $friends[$username]['friends'])) {
            $friends[$username]['friends'][] = $fromUser;
        }
        if (!in_array($username, $friends[$fromUser]['friends'])) {
            $friends[$fromUser]['friends'][] = $username;
        }
        
        // Remove from requests
        $friends[$username]['requests_received'] = array_values(
            array_filter($friends[$username]['requests_received'], fn($u) => $u !== $fromUser)
        );
        $friends[$fromUser]['requests_sent'] = array_values(
            array_filter($friends[$fromUser]['requests_sent'], fn($u) => $u !== $username)
        );
        
        saveFriends($friends);
        
        respond(true, 'Friend request accepted');
        break;
    
    // REJECT FRIEND REQUEST
    case 'rejectFriendRequest':
        $username = $_POST['username'] ?? null;
        $token = $_POST['token'] ?? null;
        $fromUser = $_POST['from_user'] ?? null;
        
        if (!$username || !$token || !$fromUser) {
            respond(false, 'Missing required parameters');
        }
        
        $users = getUsers();
        if (!isset($users[$username]) || $users[$username]['token'] !== $token) {
            respond(false, 'Unauthorized');
        }
        
        $friends = getFriends();
        if (!isset($friends[$username]['requests_received']) || !in_array($fromUser, $friends[$username]['requests_received'])) {
            respond(false, 'No friend request from this user');
        }
        
        // Remove from requests
        $friends[$username]['requests_received'] = array_values(
            array_filter($friends[$username]['requests_received'], fn($u) => $u !== $fromUser)
        );
        $friends[$fromUser]['requests_sent'] = array_values(
            array_filter($friends[$fromUser]['requests_sent'], fn($u) => $u !== $username)
        );
        
        saveFriends($friends);
        
        respond(true, 'Friend request rejected');
        break;
    
    // GET FRIENDS LIST WITH STATS
    case 'getFriendsWithStats':
        $username = $_POST['username'] ?? null;
        $token = $_POST['token'] ?? null;
        
        if (!$username || !$token) {
            respond(false, 'Username and token required');
        }
        
        $users = getUsers();
        if (!isset($users[$username]) || $users[$username]['token'] !== $token) {
            respond(false, 'Unauthorized');
        }
        
        $friends = getFriends();
        $friendsList = $friends[$username]['friends'] ?? [];
        
        $friendsData = [];
        $workouts = getWorkouts();
        
        foreach ($friendsList as $friendName) {
            if (isset($users[$friendName])) {
                $friendUser = $users[$friendName];
                $friendWorkouts = $workouts[$friendName] ?? [];
                
                // Calculate stats
                $totalReps = 0;
                $totalWorkouts = count($friendWorkouts);
                $streak = 0;
                $lastWorkoutDate = null;
                
                foreach ($friendWorkouts as $workout) {
                    if (isset($workout['reps'])) {
                        $totalReps += $workout['reps'];
                    }
                    if (isset($workout['date'])) {
                        $lastWorkoutDate = $workout['date'];
                    }
                }
                
                // Calculate average
                $avgReps = $totalWorkouts > 0 ? round($totalReps / $totalWorkouts, 1) : 0;
                
                // Get friend since date (for now, use created at)
                $friendSinceDate = isset($friends[$username]['friends_since'][$friendName]) 
                    ? $friends[$username]['friends_since'][$friendName] 
                    : $friendUser['createdAt'] ?? date('Y-m-d');
                
                $friendsData[] = [
                    'username' => $friendName,
                    'userId' => $friendUser['userId'] ?? '',
                    'profile' => $friendUser['profile'] ?? ['name' => '', 'age' => '', 'weight' => ''],
                    'total_xp' => $friendUser['total_xp'] ?? 0,
                    'total_reps' => $totalReps,
                    'avg_reps' => $avgReps,
                    'total_workouts' => $totalWorkouts,
                    'member_since' => $friendUser['createdAt'] ?? '',
                    'friends_since' => $friendSinceDate,
                    'last_workout' => $lastWorkoutDate,
                    'streak' => $streak
                ];
            }
        }
        
        respond(true, 'Friends retrieved', ['friends' => $friendsData]);
        break;
    
    // GET FRIEND REQUESTS
    case 'getFriendRequests':
        $username = $_POST['username'] ?? null;
        $token = $_POST['token'] ?? null;
        
        if (!$username || !$token) {
            respond(false, 'Username and token required');
        }
        
        $users = getUsers();
        if (!isset($users[$username]) || $users[$username]['token'] !== $token) {
            respond(false, 'Unauthorized');
        }
        
        $friends = getFriends();
        $receivedRequests = $friends[$username]['requests_received'] ?? [];
        
        $requestsData = [];
        foreach ($receivedRequests as $requesterName) {
            if (isset($users[$requesterName])) {
                $requestsData[] = [
                    'username' => $requesterName,
                    'userId' => $users[$requesterName]['userId'] ?? '',
                    'profile' => $users[$requesterName]['profile'] ?? ['name' => '', 'age' => '', 'weight' => '']
                ];
            }
        }
        
        respond(true, 'Friend requests retrieved', ['requests' => $requestsData]);
        break;
    
    // REMOVE FRIEND
    case 'removeFriend':
        $username = $_POST['username'] ?? null;
        $token = $_POST['token'] ?? null;
        $friendName = $_POST['friend_name'] ?? null;
        
        if (!$username || !$token || !$friendName) {
            respond(false, 'Missing required parameters');
        }
        
        $users = getUsers();
        if (!isset($users[$username]) || $users[$username]['token'] !== $token) {
            respond(false, 'Unauthorized');
        }
        
        $friends = getFriends();
        if (!isset($friends[$username]) || !in_array($friendName, $friends[$username]['friends'])) {
            respond(false, 'Not in friends list');
        }
        
        // Remove from both sides
        $friends[$username]['friends'] = array_values(
            array_filter($friends[$username]['friends'], fn($u) => $u !== $friendName)
        );
        if (isset($friends[$friendName]['friends'])) {
            $friends[$friendName]['friends'] = array_values(
                array_filter($friends[$friendName]['friends'], fn($u) => $u !== $username)
            );
        }
        
        saveFriends($friends);
        
        respond(true, 'Friend removed');
        break;
    
    // GET FRIENDS & REQUESTS
    case 'getFriends':
        $username = $_POST['username'] ?? null;
        $token = $_POST['token'] ?? null;
        
        if (!$username || !$token) {
            respond(false, 'Username and token required');
        }
        
        $users = getUsers();
        if (!isset($users[$username]) || $users[$username]['token'] !== $token) {
            respond(false, 'Unauthorized');
        }
        
        $friends = getFriends();
        $friendData = $friends[$username] ?? ['friends' => [], 'requests_sent' => [], 'requests_received' => []];
        
        // Get detailed info for each friend
        $friendList = [];
        foreach ($friendData['friends'] as $friendName) {
            if (isset($users[$friendName])) {
                $friendList[] = [
                    'id' => $friendName,
                    'name' => $users[$friendName]['profile']['name'] ?? $friendName,
                    'total_xp' => $users[$friendName]['total_xp'] ?? 0,
                    'streak' => 0,
                    'total_workouts' => count($users[$friendName]['workouts'] ?? []),
                    'member_since' => $users[$friendName]['createdAt'] ?? ''
                ];
            }
        }
        
        // Get incoming requests
        $incomingRequests = [];
        foreach ($friendData['requests_received'] as $requesterName) {
            if (isset($users[$requesterName])) {
                $incomingRequests[] = [
                    'id' => md5($requesterName . '_to_' . $username),
                    'from' => $requesterName,
                    'from_id' => $users[$requesterName]['userId'] ?? $requesterName,
                    'name' => $users[$requesterName]['profile']['name'] ?? $requesterName,
                    'total_xp' => $users[$requesterName]['total_xp'] ?? 0
                ];
            }
        }
        
        respond(true, 'Friends loaded', [
            'friends' => $friendList,
            'requests' => $incomingRequests,
            'count' => count($friendList)
        ]);
        break;
    
    default:
        respond(false, 'Unknown action: ' . ($action ?? 'no action provided'));
}
} catch (Throwable $e) {
    http_response_code(500);
    respond(false, 'Server error: ' . $e->getMessage());
}
?>
