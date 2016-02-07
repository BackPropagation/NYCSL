<?php

require_once 'API.class.php';

class DefHacksAPI extends API
{

	// The database
	private $mysqli;
	private $config;

	public function __construct($request, $origin) {
		$this->config = include('config.php');

		$this->initDB();

		$this->sanitizeHTTPParameters();
		
		parent::__construct($request);
	}

	private function sanitizeHTTPParameters() {
		foreach ($_GET as $key => $value) {
			$_GET[$key] = escapeshellcmd($this->mysqli->real_escape_string($value));
		}
		foreach ($_POST as $key => $value) {
			$_POST[$key] = escapeshellcmd($this->mysqli->real_escape_string($value));
		}
	}

	private function encryptPassword($password) {
		return $this->mysqli->real_escape_string(crypt($password, $this->config['salt']));
	}

	// Initializes and returns a mysqli object that represents our mysql database
	private function initDB() {
		$this->mysqli = new mysqli($this->config['hostname'], 
			$this->config['username'], 
			$this->config['password'], 
			$this->config['databaseName']);

		if (mysqli_connect_errno()) { 
			echo "<br><br>There seems to be a problem with our database. Reload the page or try again later.";
			exit(); 
		}
	}

	private function select($sql) {
		$res = mysqli_query($this->mysqli, $sql);
		if($res) return mysqli_fetch_array($res, MYSQLI_ASSOC);
		else return NULL;
	}

	private function selectMultiple($sql) {
		$res = mysqli_query($this->mysqli, $sql);
		$finalArray = array();

		while($temp = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
			array_push($finalArray, $temp);
		}

		return $finalArray;
	}

	private function insert($sql) {
		mysqli_query($this->mysqli, $sql);
	}

	private function getProblem($problemID) {
		$problemArray = $this->select("SELECT * FROM Problem WHERE problemID = {$problemID}");
			if($problemArray['isAscending'] == 1) $problemArray['submissions'] = $this->selectMultiple("SELECT * FROM Submission WHERE problemID = {$problemArray['problemID']} ORDER BY score ASC");
			else $problemArray['submissions'] = $this->selectMultiple("SELECT * FROM Submission WHERE problemID = {$problemArray['problemID']} ORDER BY score DESC");
			foreach($problemArray['submissions'] as &$submission) {
				$submission['user'] = $this->select("SELECT * FROM User WHERE userID = {$submission['userID']}");
				unset($submission['userID']);
			}
		return $problemArray;
	}

	// API ENDPOINTS
	protected function schools() {
		if($this->method == 'GET') {
			$schoolNames = array();
			$users = $this->selectMultiple("SELECT * FROM User WHERE isVerified = 1");
			foreach($users as $user) {
				$alreadyIn = false;
				foreach($schoolNames as $name) {
					if($name === $user['schoolName']) {
						$alreadyIn = true;
						break;
					} 
				}
				if($alreadyIn == false) {
					array_push($schoolNames, $user['schoolName']);
				}
			}
			return $schoolNames;
		}
	}

	protected function recover() {
		if (isset($_POST['email'])) {
			$email = $_POST['email'];
			$userIDArray = $this->select("SELECT userID, firstName, lastName FROM User WHERE email = '$email' AND isVerified = 1");
			if (count($userIDArray) > 0) {
				$userID = $userIDArray['userID'];
				$firstName = $userIDArray['firstName'];
				$lastName = $userIDArray['lastName'];

				$recoveryCode = rand(0, 99999);
				$this->insert("INSERT INTO Recovery (userID, recoveryCode) VALUES ($userID, $recoveryCode)");

				exec("php MailOperation.php \"$email\" $userID \"$firstName $lastName\" \"Click <a href='http://nycsl.io/recover.php?code={$recoveryCode}&userID={$userID}'>here</a> to change the password for $firstName $lastName at NYCSL.io. If you did not try to reset your password, ignore this message.\"> /dev/null 2>/dev/null &");
				
				// To stop email spam, sleep 10 seconds
				sleep(10);
				return "Success";
			} else {
				echo "Not in database";
				return NULL;
			}
		} else if(isset($_POST["userID"]) && isset($_POST["code"]) && isset($_POST["password"])) {
			// To stop the brute forcing of the recovery codes, sleep 1 second
			sleep(1);

			$userID = $_POST['userID'];
			$recoveryCode = $_POST['code'];
			$password = $this->encryptPassword($_POST['password']);

			$returnArray = $this->select("SELECT userID FROM Recovery WHERE userID = $userID and recoveryCode = $recoveryCode");
			if(count($returnArray) < 1) {
				return NULL;
			} else {
				$this->insert("DELETE FROM Recovery WHERE userID = $userID and recoveryCode = $recoveryCode");
				$this->insert("UPDATE User SET password = '$password' WHERE userID = $userID");
				return "Success";
			}
		} else {
			echo "No endpoint";
			return NULL;
		}
	}

	protected function session() {
		session_start();
		if($this->method == 'GET') {
			if(count($_SESSION) > 0) return $_SESSION;
			else return NULL;
		} elseif(isset($_POST['email']) & isset($_POST['password'])) {
			$email = $_POST['email'];
			$password = $this->encryptPassword($_POST['password']);
			$userArray = $this->select("SELECT * FROM User WHERE email = '$email' AND password = '$password' AND isVerified = 1");
			$_SESSION = $userArray;
		} elseif(isset($_POST['userID']) & isset($_POST['password'])) {
			$userID= $_POST['userID'];
			$password = $this->encryptPassword($_POST['password']);
			$userArray = $this->select("SELECT * FROM User WHERE userID = '$userID' AND password = '$password' AND isVerified = 1");
			$_SESSION = $userArray;
		} elseif($this->method == 'DELETE') {
			session_destroy();
		} else {
			return NULL;
		}
	}

	protected function verify() {
		if(isset($_POST["userID"]) && isset($_POST["code"])) {
			// To stop the brute forcing of the verification codes, sleep 1 second
			sleep(1);

			$userID = $_POST['userID'];
			$verificationCode = $_POST['code'];

			$returnArray = $this->select("SELECT userID FROM Verification WHERE userID = $userID and verificationCode = $verificationCode");
			if(count($returnArray) < 1) {
				return NULL;
			} else {
				$this->insert("DELETE FROM Verification WHERE userID = $userID and verificationCode = $verificationCode");
				$this->insert("UPDATE User SET isVerified = 1");
				return "Success";
			}
		} else {
			return NULL;
		}
	}

	protected function user() {
		if (isset($_GET['userID']) && isset($_GET['password'])) {
			$userID = $_GET['userID'];
			$password = $this->encryptPassword($_GET['password']);
			return $this->select("SELECT * FROM User WHERE userID = $userID and password = '$password' and isVerified = 1");
		} elseif(isset($_GET['email']) && isset($_GET['password'])) {
			$email = $_GET['email'];
			$password = $this->encryptPassword($_GET['password']);
			return $this->select("SELECT * FROM User WHERE email = '$email' and password = '$password' and isVerified = 1");
		} elseif(isset($_GET['schoolName'])) {
			$schoolName = $_GET['schoolName'];
			return $this->selectMultiple("SELECT * FROM User WHERE schoolName = '$schoolName' and isVerified = 1");
		} elseif(isset($_GET['userID'])) {
			$userID = $_GET['userID'];
			return $this->select("SELECT userID, email, schoolName, firstName, lastName FROM User WHERE userID = $userID and isVerified = 1");
		} elseif(
			isset($_POST['email']) && 
			isset($_POST['password']) &&
			isset($_POST['firstName']) &&
			isset($_POST['lastName'])) {

			$email = $_POST['email'];
			$password = $this->encryptPassword($_POST['password']);
			$firstName = $_POST['firstName'];
			$lastName = $_POST['lastName'];
			$schoolName = "";

			if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$domain = array_pop(explode('@', $email));
				if ($domain == "dalton.org") $schoolName = "Dalton";
				elseif ($domain == "horacemann.org") $schoolName = "Horace Mann";
				elseif ($domain == "riverdale.edu") $schoolName = "Riverdale Country";
				elseif ($domain == "stuy.edu") $schoolName = "Stuyvesant";
				elseif ($domain == "ecfs.org") $schoolName = "Fieldston";
				elseif ($domain == "trinityschoolnyc.org") $schoolName = "Trinity";
				elseif ($domain == "bxscience.edu") $schoolName = "Bronx Science";
				else return "School not recognized.  You must use your school email."; 
			} else return "Email is invalid.";

			$otherEmail = $this->select("SELECT * FROM User WHERE email = '".$email."'");

			if ($otherEmail !== NULL) return "Email already registered.";
			if (strlen($_POST['password']) < 4) return "Password too short.";
			if (strlen(preg_replace('/\s+/','',$firstName)) < 2 && strlen(preg_replace('/\s+/','',$lastName)) < 2) return "Must enter a valid name.";

			$this->insert("INSERT INTO User (email, password, firstName, lastName, schoolName, isVerified) VALUES ('$email', '$password', '$firstName', '$lastName', '$schoolName', 0)");

			$userIDArray = $this->select("SELECT userID FROM User WHERE email = '$email' LIMIT 1");
			$userID = $userIDArray['userID'];

			$verificationCode = rand(0, 99999);
			$this->insert("INSERT INTO Verification (userID, verificationCode) VALUES ($userID, $verificationCode)");
			
			exec("php MailOperation.php \"$email\" $userID \"$firstName $lastName\" \"Click <a href='http://nycsl.io/verify.php?code={$verificationCode}&userID={$userID}'>here</a> to confirm registration for $firstName $lastName at NYCSL.io. If you did not register, ignore this message.\"> /dev/null 2>/dev/null &");
		} elseif(isset($_GET['email'])) {
			$email = $_GET['email'];
			return $this->select("SELECT userID FROM User WHERE email = '$email' and isVerified = 1");
		} else {
			return NULL;
		}
		return "Success";
	}

	protected function problem() {
		if(isset($_GET['problemID'])) {	
			return $this->getProblem($_GET['problemID']);
		}
		if(isset($_GET['index'])) {
			$index = $_GET['index'];
			$problems = $this->select("SELECT * FROM Problem ORDER BY problemID DESC LIMIT 1 OFFSET $index");
			return $this->getProblem($problems['problemID']);
		}
		if(isset($_GET['size'])) {
			$problems = $this->selectMultiple("SELECT problemID FROM Problem");
			return count($problems);
		} 
	}

	protected function rank() {
		if(isset($_GET['submissionID'])) {
			$submissionID = $_GET['submissionID'];
			$submission = $this->select("SELECT problemID FROM Submission WHERE submissionID = $submissionID");

			$problemID = $submission['problemID'];
			$problemArray = $this->select("SELECT isAscending FROM Problem WHERE problemID = $problemID");

			$submissions = array();
			if($problemArray['isAscending'] == 0) $submissions = $this->selectMultiple("SELECT * FROM Submission WHERE problemID = $problemID ORDER BY score DESC");
			else $submissions = $this->selectMultiple("SELECT * FROM Submission WHERE problemID = $problemID ORDER BY score ASC");
			
			$place = 1;
			foreach($submissions as $otherSubmission) {
				if($otherSubmission['submissionID'] == $submissionID) {
					break;
				}
				$place++;
			}
			return $place;
		}
	}

	protected function toIndex() {
		if(isset($_GET['problemID'])) {
			$problemID = $_GET['problemID'];

			$problemArray = $this->selectMultiple("SELECT problemID FROM Problem");
			for($a = 0; $a < count($problemArray); $a++) {
				if($problemArray[$a]['problemID'] == $problemID) {
					return (count($problemArray)-1) - $a;
				}
			}
		}
	}

	protected function submission() {
		if(isset($_GET['userID'])) {
			$userID = $_GET['userID'];
			return $this->selectMultiple("SELECT * FROM Submission WHERE userID = $userID");
		} elseif(isset($_GET['submissionID'])) {
			$submissionID = $_GET['submissionID'];
			return $this->select("SELECT * FROM Submission WHERE submissionID = $submissionID");
		} elseif(isset($_GET['problemID'])) {
			$problemID = $_GET['problemID'];
			$problemArray = $this->select("SELECT * FROM Problem WHERE problemID = $problemID");
			if($problemArray['isAscending'] == 0) return $this->selectMultiple("SELECT * FROM Submission WHERE problemID = $problemID ORDER BY score DESC");
			else return $this->selectMultiple("SELECT * FROM Submission WHERE problemID = $problemID ORDER BY score ASC");
		} elseif($this->method === 'GET') {

			$problemArrayArray = $this->selectMultiple("SELECT * FROM Problem");
			$problemArray = $problemArrayArray[count($problemArrayArray)-1];

			$problemID = $problemArray['problemID'];

			if($problemArray['isAscending'] == 0) return $this->selectMultiple("SELECT * FROM Submission WHERE problemID = $problemID ORDER BY score DESC");
			else return $this->selectMultiple("SELECT * FROM Submission WHERE problemID = $problemID ORDER BY score ASC");
		} elseif(
			isset($_POST['userID']) &&
			isset($_FILES['outputFile']['name'])) {

			// Parameters
			$userID = $_POST['userID'];
			
			// Last one is current one
			$problemArrayArray = $this->selectMultiple("SELECT * FROM Problem");
			$problemArray = $problemArrayArray[count($problemArrayArray)-1];
			$problemID = $problemArray['problemID'];
			$problemName = $problemArray['problemName'];
			$isAscending = $problemArray['isAscending'];

			// Mark submission not ready
			$submissionArray = $this->select("SELECT isReady, submissionID FROM Submission WHERE problemID = $problemID and userID = $userID");
			if(count($submissionArray) > 0) {
				$this->insert("UPDATE Submission SET isReady = 0 WHERE submissionID = {$submissionArray['submissionID']}");
			}
			$targetPath = "../problems/outputs/{$problemName}/";
			if(!file_exists($targetPath)) mkdir($targetPath);
			$ext = explode('.', basename( $_FILES['outputFile']['name']));
			$targetPath = $targetPath . $userID . "." . $ext[count($ext)-1];
			if(file_exists($targetPath)) unlink($targetPath);
			clearstatcache();
			move_uploaded_file($_FILES['outputFile']['tmp_name'], $targetPath);

			// Pass target file to python script
			exec("python3 ../problems/scripts/$problemName.py $targetPath", $rawOutput);
			
			if(!isset($rawOutput[0])) return array("isError" => true, "message" => "There was a problem with your submission file.");
			$programOutput = json_decode($rawOutput[count($rawOutput)-1]);
			
			// Some problems dont return score from their submission scripts
			// For example, if it were an ai game competition, where bots play against one another
			if(isset($programOutput->score)) {
				$userArray = $this->select("SELECT * FROM Submission WHERE userID = $userID and problemID = $problemID");
				if($userArray['userID'] != NULL) {
					if(($isAscending == true && $userArray['score'] > $programOutput->score) || ($isAscending == false && $userArray['score'] < $programOutput->score) || $problemArray['doReset'] == true) {		
						$this->insert("UPDATE Submission SET score = {$programOutput->score}, isReady = 1 WHERE userID = $userID and problemID = $problemID");	
					}
				} else {
					$this->insert("INSERT INTO Submission (problemID, userID, score, isReady) VALUES ($problemID, $userID, {$programOutput->score}, 1)");
				}
			}
			return $programOutput;
		} else {
			return "Didn't reach endpoint";
		}
		return "Success";
	}

	protected function game() {
		if(isset($_GET['userID'])) {
			$limit = isset($_GET['limit']) ? $_GET['limit'] : 10;
			$userID = $_GET['userID'];

			$gameIDArrays = $this->selectMultiple("SELECT * FROM GameToUser WHERE userID = $userID ORDER BY gameID DESC LIMIT $limit");
			$gameArrays = array();
			foreach ($gameIDArrays as $gameIDArray) {
				$gameID = $gameIDArray['gameID'];
				$gameArray = $this->select("SELECT * FROM Game WHERE gameID = $gameID");
				$gameArray['users'] = $this->selectMultiple("SELECT userID, rank, playerIndex FROM GameToUser WHERE gameID = $gameID");
				foreach($gameArray['users'] as &$gameUserRow) {
					$gameUserRank = $gameUserRow['rank'];
					$gameUserRow = $this->select("SELECT * FROM User WHERE userID = {$gameUserRow['userID']}");
					$gameUserRow['rank'] = $gameUserRank;
				}
				array_push($gameArrays, $gameArray);
			}
			return $gameArrays;
		}
		return NULL;
	}
 }

 ?>
