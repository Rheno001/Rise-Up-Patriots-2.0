<?php
/**
 * Registration Model
 * 
 * This class handles all database operations related to user registrations
 */

require_once __DIR__ . '/../config/database.php';

class Registration {
    private $conn;
    private $table_name = "registrations";

    // Registration properties
    public $id;
    public $title;
    public $gender;
    public $first_name;
    public $last_name;
    public $phone;
    public $email;
    public $age_range;
    public $attendance_type;
    public $country_code;
    public $country_name;
    public $state_of_origin;
    public $how_did_you_hear;
    public $registration_date;
    public $status;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        
        if ($this->conn === null) {
            throw new Exception("Failed to establish database connection");
        }
    }

    /**
     * Create a new registration
     * 
     * @return bool True on success, false on failure
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET title=:title, 
                      gender=:gender, 
                      first_name=:first_name, 
                      last_name=:last_name, 
                      phone=:phone, 
                      email=:email, 
                      age_range=:age_range, 
                      attendance_type=:attendance_type, 
                      country_code=:country_code, 
                      country_name=:country_name, 
                      state_of_origin=:state_of_origin, 
                      how_did_you_hear=:how_did_you_hear";

        $stmt = $this->conn->prepare($query);

        // Sanitize input
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->gender = htmlspecialchars(strip_tags($this->gender));
        $this->first_name = htmlspecialchars(strip_tags($this->first_name));
        $this->last_name = htmlspecialchars(strip_tags($this->last_name));
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->age_range = htmlspecialchars(strip_tags($this->age_range));
        $this->attendance_type = htmlspecialchars(strip_tags($this->attendance_type));
        $this->country_code = htmlspecialchars(strip_tags($this->country_code));
        $this->country_name = htmlspecialchars(strip_tags($this->country_name));
        $this->state_of_origin = htmlspecialchars(strip_tags($this->state_of_origin));
        $this->how_did_you_hear = htmlspecialchars(strip_tags($this->how_did_you_hear));

        // Bind parameters
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":gender", $this->gender);
        $stmt->bindParam(":first_name", $this->first_name);
        $stmt->bindParam(":last_name", $this->last_name);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":age_range", $this->age_range);
        $stmt->bindParam(":attendance_type", $this->attendance_type);
        $stmt->bindParam(":country_code", $this->country_code);
        $stmt->bindParam(":country_name", $this->country_name);
        $stmt->bindParam(":state_of_origin", $this->state_of_origin);
        $stmt->bindParam(":how_did_you_hear", $this->how_did_you_hear);

        try {
            if ($stmt->execute()) {
                $this->id = $this->conn->lastInsertId();
                return true;
            }
            
            // Log the error info if execution failed
            $errorInfo = $stmt->errorInfo();
            error_log("Registration creation failed - SQL Error: " . json_encode($errorInfo));
            return false;
            
        } catch (PDOException $e) {
            error_log("Registration creation PDO exception: " . $e->getMessage());
            throw new Exception("Database error during registration: " . $e->getMessage());
        }
    }

    /**
     * Check if email already exists
     * 
     * @param string $email Email to check
     * @return bool True if exists, false otherwise
     */
    public function emailExists($email) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Get registration by ID
     * 
     * @param int $id Registration ID
     * @return array|false Registration data or false if not found
     */
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Get registration by email
     * 
     * @param string $email Email address
     * @return array|false Registration data or false if not found
     */
    public function getByEmail($email) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Get all registrations with pagination
     * 
     * @param int $page Page number
     * @param int $limit Records per page
     * @return array Array of registrations
     */
    public function getAll($page = 1, $limit = 50) {
        $offset = ($page - 1) * $limit;
        
        $query = "SELECT * FROM " . $this->table_name . " 
                  ORDER BY registration_date DESC 
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get total count of registrations
     * 
     * @return int Total count
     */
    public function getTotalCount() {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch();
        
        return $row['total'];
    }

    /**
     * Update registration status
     * 
     * @param int $id Registration ID
     * @param string $status New status
     * @return bool True on success, false on failure
     */
    public function updateStatus($id, $status) {
        $query = "UPDATE " . $this->table_name . " SET status = :status WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":status", $status);
        $stmt->bindParam(":id", $id);

        return $stmt->execute();
    }

    /**
     * Get registrations by country
     * 
     * @param string $country_code Country code
     * @return array Array of registrations
     */
    public function getByCountry($country_code) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE country_code = :country_code 
                  ORDER BY registration_date DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":country_code", $country_code);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get registration statistics
     * 
     * @return array Statistics data
     */
    public function getStatistics() {
        $stats = [];

        // Total registrations
        $stats['total'] = $this->getTotalCount();

        // Status-based statistics
        $query = "SELECT 
                    COUNT(CASE WHEN status = 'pending' OR status IS NULL THEN 1 END) as pending,
                    COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed,
                    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled
                  FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch();
        $stats['pending'] = $row['pending'];
        $stats['confirmed'] = $row['confirmed'];
        $stats['cancelled'] = $row['cancelled'];

        // Registrations by attendance type
        $query = "SELECT attendance_type, COUNT(*) as count 
                  FROM " . $this->table_name . " 
                  GROUP BY attendance_type";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['by_attendance'] = $stmt->fetchAll();

        // Registrations by country
        $query = "SELECT country_name, COUNT(*) as count 
                  FROM " . $this->table_name . " 
                  GROUP BY country_name 
                  ORDER BY count DESC 
                  LIMIT 10";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['by_country'] = $stmt->fetchAll();

        // Registrations by age range
        $query = "SELECT age_range, COUNT(*) as count 
                  FROM " . $this->table_name . " 
                  GROUP BY age_range";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['by_age'] = $stmt->fetchAll();

        // Recent registrations (last 7 days)
        $query = "SELECT COUNT(*) as count 
                  FROM " . $this->table_name . " 
                  WHERE registration_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch();
        $stats['recent'] = $row['count'];

        return $stats;
    }

    /**
     * Validate registration data
     * 
     * @param array $data Registration data
     * @return array Array of validation errors (empty if valid)
     */
    public function validate($data) {
        $errors = [];

        // Required fields
        $required_fields = [
            'title', 'gender', 'firstName', 'lastName', 
            'phone', 'email', 'ageRange', 'attendanceType', 
            'country', 'stateOfOrigin', 'howDidYouHear'
        ];

        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $errors[] = ucfirst(str_replace(['_', 'Of'], [' ', ' of '], $field)) . " is required";
            }
        }

        // Email validation
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }

        // Phone validation (basic)
        if (!empty($data['phone']) && !preg_match('/^[\+]?[0-9\s\-\(\)]{10,20}$/', $data['phone'])) {
            $errors[] = "Invalid phone number format";
        }

        // Check if email already exists
        if (!empty($data['email']) && $this->emailExists($data['email'])) {
            $errors[] = "Email address is already registered";
        }

        return $errors;
    }
}
?>