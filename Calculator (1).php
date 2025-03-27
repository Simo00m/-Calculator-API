<?php

header("Content-Type: application/json");

$method = $_SERVER["REQUEST_METHOD"];

// Database configuration
$host = "localhost";
$user = "root";
$password = "";
$database = "calculator";

// Create database connection
$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(["error" => "Database connection failed."]));
}

$user = [];

/**
 * Verify user credentials.
 */
function verifyUser($username, $password) {
    global $user, $conn;

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $user = $row;
        return true;
    }
    return false;
}

/**
 * Handle errors and exit.
 */
function error($message) {
    http_response_code(400);
    echo json_encode(["error" => $message]);
    exit;
}

/**
 * Perform a calculation.
 */
function calculate($num1, $num2, $operation) {
    switch ($operation) {
        case "+": return $num1 + $num2;
        case "-": return $num1 - $num2;
        case "*": return $num1 * $num2;
        case "/":
            if ($num2 == 0) error("Cannot divide by 0.");
            return $num1 / $num2;
        default: error("Invalid operation.");
    }
}

// Handle POST request
if ($method === "POST") {
    $input = json_decode(file_get_contents("php://input"), true);

    if (!isset($input["username"], $input["password"], $input["num1"], $input["num2"], $input["operation"])) {
        error("Missing parameters.");
    }

    if (!verifyUser($input["username"], $input["password"])) {
        error("Invalid credentials.");
    }

    $num1 = floatval($input["num1"]);
    $num2 = floatval($input["num2"]);
    $operation = $input["operation"];
    $result = calculate($num1, $num2, $operation);

    // Save calculation to history
    $stmt = $conn->prepare("INSERT INTO history (userid, num1, num2, operation, result) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iddss", $user['id'], $num1, $num2, $operation, $result);
    $stmt->execute();

    echo json_encode([
        "message" => "Calculation!",
        "calculation" => [
            "id" => $user['id'],
            "num1" => $num1,
            "num2" => $num2,
            "operation" => $operation,
            "result" => $result,
            "timestamp" => date("Y-m-d H:i:s")
        ]
    ]);
    exit;
}

// Handle GET request
if ($method === "GET") {
    $input = json_decode(file_get_contents("php://input"), true);

    if (!isset($input["username"], $input["password"])) {
        error("Missing credentials.");
    }

    if (!verifyUser($input["username"], $input["password"])) {
        error("Invalid credentials.");
    }

    $stmt = $conn->prepare("SELECT * FROM history WHERE userid = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();

    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }

    echo json_encode(["history" => $history]);
    exit;
}

// Handle DELETE request
if ($method === "DELETE") {
    parse_str(file_get_contents("php://input"), $input);

    if (!isset($input["id"])) {
        error("Missing entry ID.");
    }

    $id = intval($input["id"]);
    $stmt = $conn->prepare("DELETE FROM history WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(["message" => "Entry deleted."]);
    } else {
        error("Invalid entry ID.");
    }
}

$conn->close();
?>