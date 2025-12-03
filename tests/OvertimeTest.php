<?php
use PHPUnit\Framework\TestCase;

// Mock the PDO and PDOStatement classes
class MockPDOStatement extends \PDOStatement {
    private $fetch_result;

    public function __construct($fetch_result = null) {
        $this->fetch_result = $fetch_result;
    }
    public function execute(?array $params = null): bool {
        return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed {
        return $this->fetch_result;
    }
}

class MockPDO extends \PDO {
    private $mock_results = [];
    public function __construct() {}
    public function setQueryResult($query_substring, $result) {
        $this->mock_results[$query_substring] = $result;
    }
    public function prepare(string $query, array $options = []): \PDOStatement|false {
        foreach ($this->mock_results as $substring => $result) {
            if (strpos($query, $substring) !== false) {
                return new MockPDOStatement($result);
            }
        }
        return new MockPDOStatement(false); // Default mock statement
    }
}

class OvertimeTest extends TestCase
{
    protected $backup_globals = true;
    protected $pdo;

    protected function setUp(): void
    {
        // Suppress header output
        ob_start();

        // Mock global session and POST variables
        $_SESSION = [
            'employee_id' => 1,
            'logged_in' => true,
            'usertype' => 2,
            'fullname' => 'Test User'
        ];

        $_POST = [];

        // Setup the mock PDO object for all tests
        $this->pdo = new MockPDO();
    }

    protected function tearDown(): void
    {
        // Clean up output buffer and globals
        ob_end_clean();
        $_SESSION = [];
        $_POST = [];
    }

    public function testRejectsInvalidOvertimeHours()
    {
        global $pdo;
        $pdo = $this->pdo;

        // Simulate a POST request with invalid hours (less than 0.5)
        $_POST['submit_ot'] = '1';
        $_POST['ot_date'] = date('Y-m-d');
        $_POST['ot_hours'] = '0.4';
        $_POST['reason'] = 'Testing invalid hours';

        // Include the script to be tested
        // It will set the global $submission_message variable
        include __DIR__ . '/../user/file_overtime.php';

        // Assert that the submission was rejected with the correct error message
        $this->assertIsArray($submission_message, "submission_message was not set");
        $this->assertEquals('danger', $submission_message['type']);
        $this->assertEquals('Requested hours must be a valid number between 0.5 and 8.', $submission_message['text']);
    }

    public function testAcceptsValidOvertimeHours()
    {
        global $pdo;
        $pdo = $this->pdo;

        // Configure mock PDO results for a successful submission
        // 1. Employee has raw OT hours logged
        $this->pdo->setQueryResult('SELECT overtime_hr FROM tbl_attendance', ['overtime_hr' => 2]);
        // 2. No existing pending request
        $this->pdo->setQueryResult("SELECT id FROM tbl_overtime", false);

        // Simulate a POST request with valid hours
        $_POST['submit_ot'] = '1';
        $_POST['ot_date'] = date('Y-m-d');
        $_POST['ot_hours'] = '0.5';
        $_POST['reason'] = 'Testing valid hours';

        // Include the script to be tested
        include __DIR__ . '/../user/file_overtime.php';

        // Assert that the submission was successful
        $this->assertIsArray($submission_message, "submission_message was not set");
        $this->assertEquals('success', $submission_message['type']);
        $this->assertEquals('Overtime request submitted successfully! It is now pending approval.', $submission_message['text']);
    }
}
