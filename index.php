<?php
session_start();

// Show all errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database Connection Class
class Database {
    private $host = 'localhost';
    private $db_name = 'travel_app';
    private $username = 'root';
    private $password = '';
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=$this->host;dbname=$this->db_name;charset=utf8", $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            die("Connection error: " . $exception->getMessage());
        }
        return $this->conn;
    }
}

// User Class
class User {
    private $conn;
    private $table = 'users';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function register($username, $email, $password) {
        // Validate password length minimal 6 karakter
        if (strlen($password) < 6) {
            throw new Exception("Password must be at least 6 characters.");
        }

        // Check existing username or email
        $stmt = $this->conn->prepare("SELECT id FROM $this->table WHERE username = :username OR email = :email");
        $stmt->execute(['username' => $username, 'email' => $email]);

        if ($stmt->rowCount() > 0) {
            throw new Exception("Username or email already taken.");
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->conn->prepare("INSERT INTO $this->table (username, email, password) VALUES (:username, :email, :password)");
        return $stmt->execute(['username' => $username, 'email' => $email, 'password' => $hash]);
    }

    public function login($usernameOrEmail, $password) {
        $stmt = $this->conn->prepare("SELECT * FROM $this->table WHERE username = :ue OR email = :ue");
        $stmt->execute(['ue' => $usernameOrEmail]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true); // Tambahan ini untuk keamanan session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            return true;
        }

        throw new Exception("Invalid login credentials.");
    }

    public function logout() {
        session_destroy();
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function getUsername() {
        return $_SESSION['username'] ?? null;
    }
}

// Model classes for other data
class Destination {
    private $conn;
    private $table = 'destinations';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        $stmt = $this->conn->query("SELECT * FROM $this->table");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

class TravelPackage {
    private $conn;
    private $table = 'travel_packages';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        $stmt = $this->conn->query("SELECT * FROM $this->table");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

class Promotion {
    private $conn;
    private $table = 'promotions';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getActivePromotions() {
        $today = date('Y-m-d');
        $stmt = $this->conn->prepare("SELECT * FROM $this->table WHERE start_date <= :today AND end_date >= :today");
        $stmt->execute(['today' => $today]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

class Stats {
    private $conn;
    private $table = 'stats';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function get() {
        $stmt = $this->conn->query("SELECT * FROM $this->table LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Initialize variables
$message = '';
$messageRegister = '';
$messageLogin = '';
$user = null;

try {
    $database = new Database();
    $db = $database->getConnection();

    $user = new User($db);
    $destModel = new Destination($db);
    $packModel = new TravelPackage($db);
    $promoModel = new Promotion($db);
    $statsModel = new Stats($db);

    // Handle POST actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['register'])) {
            try {
                $user->register($_POST['username'], $_POST['email'], $_POST['password']);
                $messageRegister = "Registration successful. Please login.";
            } catch (Exception $e) {
                $messageRegister = $e->getMessage();
            }
        } elseif (isset($_POST['login'])) {
            try {
                $user->login($_POST['username_email'], $_POST['password']);
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } catch (Exception $e) {
                $messageLogin = $e->getMessage();
            }
        } elseif (isset($_POST['logout'])) {
            $user->logout();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }

    // Load data
    $destinations = $destModel->getAll();
    $packages = $packModel->getAll();
    $promotions = $promoModel->getActivePromotions();
    $stats = $statsModel->get();

} catch (Exception $e) {
    $message = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Travel Landing Page</title>
<!-- Bootstrap CSS CDN -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<style>
.hero {
    background: url('images/hero.jpg') center/cover no-repeat;
    height: 100vh;
    color: white;
    text-align: center;
    position: relative;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.hero h1 span {
    color: #ffd700;
}
.destination-img {
    width: 100%;
    height: 140px;
    object-fit: cover;
    border-radius: 0.375rem;
}
.promo-section {
    background: url('images/promo.jpg') center/cover no-repeat;
    color: white;
    padding: 4rem 2rem;
    text-align: center;
}
</style>
<script>
function toggleForms() {
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    if (loginForm.style.display === 'none') {
        loginForm.style.display = 'block';
        registerForm.style.display = 'none';
    } else {
        loginForm.style.display = 'none';
        registerForm.style.display = 'block';
    }
}
</script>
</head>
<body>
<div class="position-fixed top-0 end-0 p-3" style="z-index:1050; max-width:320px;">
    <div class="card p-3 shadow bg-white">
        <?php if ($user && $user->isLoggedIn()): ?>
            <div class="mb-2">Welcome, <strong><?= htmlspecialchars($user->getUsername()) ?></strong>!</div>
            <form method="post">
                <button type="submit" name="logout" class="btn btn-danger w-100">Logout</button>
            </form>
        <?php else: ?>
            <div id="login-form">
                <h5>Login</h5>
                <?php if ($messageLogin): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($messageLogin) ?></div>
                <?php elseif ($messageRegister): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($messageRegister) ?></div>
                <?php endif; ?>
                <form method="post" class="mb-3">
                    <input type="text" name="username_email" placeholder="Username or Email" class="form-control mb-2" required />
                    <input type="password" name="password" placeholder="Password" class="form-control mb-2" required />
                    <button type="submit" name="login" class="btn btn-primary w-100">Login</button>
                </form>
                <a href="javascript:void(0)" onclick="toggleForms()">Don't have an account? Register</a>
            </div>
            <div id="register-form" style="display:none;">
                <h5>Register</h5>
                <?php if ($messageRegister && !$messageLogin): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($messageRegister) ?></div>
                <?php endif; ?>
                <form method="post" class="mb-3">
                    <input type="text" name="username" placeholder="Username" class="form-control mb-2" required />
                    <input type="email" name="email" placeholder="Email" class="form-control mb-2" required />
                    <input type="password" name="password" placeholder="Password" class="form-control mb-2" required />
                    <button type="submit" name="register" class="btn btn-success w-100">Register</button>
                </form>
                <a href="javascript:void(0)" onclick="toggleForms()">Already have an account? Login</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<header class="hero">
    <h1>Travel with <span>Peace of Mind</span></h1>
    <p>Discover the world with us</p>
</header>

<section class="container my-5">
    <h2 class="mb-4">Popular Travel Packages</h2>
    <div class="row">
        <?php if (!empty($packages)): ?>
            <?php foreach ($packages as $p): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 shadow-sm">
                        <img src="<?= htmlspecialchars($p['image'] ?? 'images/default-package.jpg') ?>" class="card-img-top" alt="<?= htmlspecialchars($p['title'] ?? 'Package') ?>" />
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?= htmlspecialchars($p['title'] ?? 'No Title') ?></h5>
                            <p class="card-text flex-grow-1"><?= htmlspecialchars($p['description'] ?? '') ?></p>
                            <strong class="text-primary">Price: $<?= htmlspecialchars($p['price'] ?? '0') ?></strong>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No travel packages available.</p>
        <?php endif; ?>
    </div>
</section>

<section class="container my-5">
    <h2 class="mb-4">Travel Statistics</h2>
    <?php if ($stats): ?>
        <div class="row text-center">
            <div class="col-sm-3"><strong><?= (int)($stats['destinations'] ?? 0) ?></strong><br />Destinations</div>
            <div class="col-sm-3"><strong><?= (int)($stats['tours'] ?? 0) ?></strong><br />Tours</div>
            <div class="col-sm-3"><strong><?= (int)($stats['cruises'] ?? 0) ?></strong><br />Cruises</div>
            <div class="col-sm-3"><strong><?= (int)($stats['hotels'] ?? 0) ?></strong><br />Hotels</div>
        </div>
    <?php else: ?>
        <p>No statistics data available.</p>
    <?php endif; ?>
</section>

<section class="container my-5">
    <h2 class="mb-4">Popular Destinations</h2>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 g-3">
        <?php if (!empty($destinations)): ?>
            <?php foreach ($destinations as $d): ?>
                <div class="col">
                    <div class="card h-100 shadow-sm">
                        <img src="<?= htmlspecialchars($d['image'] ?? 'images/default-destination.jpg') ?>" alt="<?= htmlspecialchars($d['name'] ?? 'Destination') ?>" class="destination-img" />
                        <div class="card-body p-2">
                            <span class="badge bg-warning text-dark"><?= htmlspecialchars($d['region'] ?? 'Unknown') ?></span>
                            <h6 class="mt-2 mb-0"><?= htmlspecialchars($d['name'] ?? 'Unnamed') ?></h6>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No destinations available.</p>
        <?php endif; ?>
    </div>
</section>

<section class="promo-section my-5">
    <h2>Current Promotions</h2>
    <?php if (!empty($promotions)): ?>
        <?php foreach ($promotions as $promo): ?>
            <p><?= htmlspecialchars($promo['description'] ?? '') ?></p>
            <p><strong>Discount: <?= htmlspecialchars($promo['discount'] ?? '0') ?>%</strong></p>
            <hr />
        <?php endforeach; ?>
    <?php else: ?>
        <p>No current promotions.</p>
    <?php endif; ?>
</section>

<footer class="text-center py-4 bg-light">
    <small>&copy; <?= date('Y') ?> Travel App. All rights reserved.</small>
</footer>

<!-- Bootstrap JS Bundle CDN -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
