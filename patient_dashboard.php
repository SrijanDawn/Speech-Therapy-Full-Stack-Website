<?php
session_start();
if ($_SESSION['userType'] != 'patient') {
    header("Location: login.html");
    exit();
}

// Database connections
$conn_patient = new mysqli("localhost", "root", "", "speech_therapy_clinic_patient");

$conn_approved = new mysqli("localhost", "root", "", "speech_therapy_clinic_approved");

$conn_appointment = new mysqli("localhost", "root", "", "speech_therapy_clinic_pending_appointment");

// Check connections
if ($conn_patient->connect_error) {
    die("Connection failed (patient database): " . $conn_patient->connect_error);
}
if ($conn_approved->connect_error) {
    die("Connection failed (approved database): " . $conn_approved->connect_error);
}
if ($conn_appointment->connect_error) {
    die("Connection failed (pending appointment database): " . $conn_appointment->connect_error);
}

// Fetch patient data
$email = $_SESSION['email'];
$sql = "SELECT * FROM patients WHERE email='$email'";
$result = $conn_patient->query($sql);
$user = $result->fetch_assoc();

$doctorData = null;
$appointments = [];
if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST['doctor_name'])) {
    $doctor_name = $conn_approved->real_escape_string($_POST['doctor_name']);
    
    // Query the approved database for doctors including rating
    $sql_approved = "SELECT * FROM approved_therapists WHERE fullname LIKE '%$doctor_name%'";
    $result_approved = $conn_approved->query($sql_approved);
    
    if ($result_approved->num_rows > 0) {
        $doctorData = $result_approved->fetch_all(MYSQLI_ASSOC);
    } else {
        $doctorData = "No doctor found with that name.";
    }
}

// Handle appointment submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['book_appointment'])) {
    $patient_name = $conn_appointment->real_escape_string($_POST['patient_name']);
    $patient_phone = $conn_appointment->real_escape_string($_POST['patient_phone']);
    $preferred_date = $conn_appointment->real_escape_string($_POST['preferred_date']);
    $preferred_time = $conn_appointment->real_escape_string($_POST['preferred_time']);
    $disease = $conn_appointment->real_escape_string($_POST['disease']);
    $about = $conn_appointment->real_escape_string($_POST['about']);
    $therapist_name = $conn_appointment->real_escape_string($_POST['therapist_name']);
    $username = $conn_appointment->real_escape_string($user['fullname']);

    $sql_appointment = "INSERT INTO pending_appointment (username, patient_name, patient_phone, preferred_date, preferred_time, disease, about, therapist_name) 
                        VALUES ('$username', '$patient_name', '$patient_phone', '$preferred_date', '$preferred_time', '$disease', '$about', '$therapist_name')";
    
    if ($conn_appointment->query($sql_appointment) === TRUE) {
        echo "<script>alert('Appointment booked successfully!');</script>";
    } else {
        echo "Error: " . $sql_appointment . "<br>" . $conn_appointment->error;
    }
}

// Fetch patient appointments
$sql_appointments = "SELECT * FROM pending_appointment WHERE username='{$user['fullname']}'";
$result_appointments = $conn_appointment->query($sql_appointments);
if ($result_appointments->num_rows > 0) {
    $appointments = $result_appointments->fetch_all(MYSQLI_ASSOC);
}

$conn_patient->close();
$conn_approved->close();
$conn_appointment->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard</title>
        <!-- css -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="assests/css/patient_dashboard.css">
    <!-- js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script defer type="text/javascript" src="assests/js/patient_dashboard.js"></script>
    <style>
        /*.profile-button{
            position: fixed;
            top: 30px;
            right: 30px;
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 30px 30px;
            cursor: pointer;
            z-index: 1000; /* Ensure the button is above other content */
        }*/
        .my-appointments-button {
            position: fixed;
            top: 10px;
            right: 10px;
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            z-index: 1000; /* Ensure the button is above other content */
        }
        .modal, .appointment-modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
            padding-top: 60px;
        }
        .modal-content, .appointment-modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
        input[type="text"], input[type="date"], input[type="time"] {
            padding: 10px;
            width: calc(100% - 22px);
            margin-bottom: 10px;
        }
        input[type="submit"], .book-button {
            padding: 10px 20px;
/*            background-color: #4CAF50;*/
            color: white;
            border: none;
            cursor: pointer;
            margin-left: 200px;
            margin-top: 50px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .dropdown {
            position: relative;
            display: inline-block;
        }
        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #f9f9f9;
            min-width: 300px; /* Adjust width as needed */
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            max-height: 300px; /* Adjust height as needed */
            overflow-y: auto;
            border-radius: 5px;
        }
        .dropdown-content a {
            color: black;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
        }
        .dropdown-content a:hover {background-color: #f1f1f1}
        .dropdown:hover .dropdown-content {display: block}
        .dropdown:hover .my-appointments-button {background-color: #3e8e41}
    </style>
    <script>
        function showModal() {
            document.getElementById("profileModal").style.display = "block";
        }
        function closeModal() {
            document.getElementById("profileModal").style.display = "none";
        }
        function showAppointmentModal(therapistName) {
            document.getElementById("therapistName").value = therapistName;
            document.getElementById("appointmentModal").style.display = "block";
        }
        function closeAppointmentModal() {
            document.getElementById("appointmentModal").style.display = "none";
        }
        window.onclick = function(event) {
            if (event.target == document.getElementById("profileModal")) {
                document.getElementById("profileModal").style.display = "none";
            }
            if (event.target == document.getElementById("appointmentModal")) {
                document.getElementById("appointmentModal").style.display = "none";
            }
        }
    </script>

    <!-- scripts for desings in navbar and sidebar -->
        <script src="https://unpkg.com/phosphor-icons"></script>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
        <link
      href="https://cdn.jsdelivr.net/npm/remixicon@3.4.0/fonts/remixicon.css"
      rel="stylesheet"
    />


    <!-- Navbar and sidebar -->

        <nav class="section__container nav__container gradient-custom1">
        <div class="nav__logo"><a href="/">Speech<span>Therapy</a></span></div>
        <ul class="nav__links">
         <li class="link"><a href="/#home">Home</a></li>
          <li class="link"><a href="/#about">About Us</a></li>
          <li class="link"><a href="/#service">Services</a></li>
          <li class="link"><a href="/#pages">Therapists</a></li>
          <li class="link"><a href="/#contact_us">Contact us</a></li>
        </ul>
        <div class="search_login">
        <form class="d-flex nav-search-form" role="search">
              <input class="search-box" type="search" placeholder="Search" aria-label="Search">
              <!-- <button class="btn btn-outline-success" type="submit">Search</button> -->
              <button class="search-btn" type="button"><i class="ph-bold ph-magnifying-glass"></i></button>
              <button class="close-btn" type="button"><i class="ph-bold ph-x"></i></button>
        </form>
        <!-- <button class="notification_bell" type="button"><i class="ph ph-bell"></i></button> -->
        <button class="notification_bell" type="button"><i class='bx bx-bell'></i></button>
        <!-- {% if session.get('email') %} -->
          <button type="button" class="btn profile-button" onclick="showModal()">Profile</button>
          <!-- <button> class="profile-button" onclick="showModal()">Profile</button> -->

        <!-- Button for My Appointments -->
        <div class="dropdown">
            <button class="my-appointments-button btn">My Appointments</button>
            <div class="dropdown-content">
                <?php if (count($appointments) > 0): ?>
                    <?php foreach ($appointments as $appointment): ?>
                        <a href="#" onclick="alert('Patient Name: <?php echo htmlspecialchars($appointment['patient_name']); ?>\nDoctor/Therapist: <?php echo htmlspecialchars($appointment['therapist_name']); ?>\nDate: <?php echo htmlspecialchars($appointment['preferred_date']); ?>\nTime: <?php echo htmlspecialchars($appointment['preferred_time']); ?>\nDisease: <?php echo htmlspecialchars($appointment['disease']); ?>\nAbout: <?php echo htmlspecialchars($appointment['about']); ?>')">
                            <?php echo htmlspecialchars($appointment['preferred_date']); ?> at <?php echo htmlspecialchars($appointment['preferred_time']); ?> - <?php echo htmlspecialchars($appointment['patient_name']); ?> (Therapist: <?php echo htmlspecialchars($appointment['therapist_name']); ?>)
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <a href="#">No appointments found.</a>
                <?php endif; ?>
            </div>
        </div>


          <a href="logout.php"><button type="button" class="btn">Log out</button></a>
        <!-- {% else %}
          <a href="/register"><button type="button" class="btn">Sign Up</button></a>
          <a href="/login"><button type="button" class="btn">Log in</button></a>
        {% endif %} -->
      </div>
      </nav>
    
    <nav class="sidebar">
      <div class="logo_menu">
        <h2 class="sidebar_logo">Menu</h2>
        <i class='bx bx-menu toggle_btn'></i>
      </div>
      <ul class="list">
        <li class="list_item active_sidebar_element">
          <a href="#">
            <i class='bx bx-grid-alt' ></i>
            <span class="link_name" style="--i:1;">Dashboard</span>
          </a>
        </li>
        <li class="list_item">
          <a href="#">
            <i class='bx bx-home' ></i>
            <span class="link_name" style="--i:2;">Home</span>
          </a>
        </li>
        <li class="list_item">
          <a href="#">
            <i class='bx bxs-watch' ></i>
            <span class="link_name" style="--i:3;">Appointments</span>
          </a>
        </li>
        <li class="list_item">
          <a href="#">
            <i class='bx bx-laptop' ></i>
            <span class="link_name" style="--i:4;">Online Therapy</span>
          </a>
        </li>
        <li class="list_item">
          <a href="#">
            <i class='bx bx-bell' ></i>
            <span class="link_name" style="--i:5;">Notifications</span>
          </a>
        </li>
        <li class="list_item">
          <a href="#">
            <i class='bx bx-cog' ></i>
            <span class="link_name" style="--i:6;">Settings</span>
          </a>
        </li>
      </ul>
    </nav>
</head>
<body>
    <div class="welcome_patient_content">
        <div class="welcome_patient_header">
            <h2>Welcome, <?php echo htmlspecialchars($user['fullname']); ?></h2>
            
        </div>
    </div>
    <!-- <button class="profile-button" onclick="showModal()">Profile</button> -->
    
     <!-- Button for My Appointments -->
<!--    <div class="dropdown">
        <button class="my-appointments-button">My Appointments</button>
        <div class="dropdown-content">
            
        </div>
    </div>
 -->


    <div id="profileModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Profile Details</h2>
            <p><strong>Full Name:</strong> <?php echo htmlspecialchars($user['fullname']); ?></p>
            <p><strong>Gender:</strong> <?php echo htmlspecialchars($user['gender']); ?></p>
            <p><strong>Contact No.:</strong> <?php echo htmlspecialchars($user['contact']); ?></p>
            <p><strong>Email ID:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
        </div>
    </div>

    <div class="search_doctor">
        <h2>Search for Doctors</h2>
        <form method="POST" class="search_doctor_form">
            <label for="doctor_name">Enter Doctor's Name:</label>
            <input type="text" class="doctor_search_input" id="doctor_name" name="doctor_name">
            <div class="search_doctor_form_btn">
                <input type="submit" class="btn" value="Search">
              
            </div>
        </form>
    </div>
    <button type="submit" id="therapists-near-me-btn"  class="btn therapists-near-me-btn">therapists near me</button>


    <?php if ($doctorData): ?>
        <?php if (is_array($doctorData)): ?>
            <table class="search_result_table">
                <tr>
                    <th>Full Name</th>
                    <th>Date of Birth</th>
                    <th>Gender</th>
                    <th>Contact No.</th>
                    <th>License Number</th>
                    <th>Education</th>
                    <th>Experience</th>
                    <th>Specializations</th>
                    <th>Photo</th>
                    <th>Certificate</th>
                    <th>Rating</th> <!-- New Column for Rating -->
                </tr>
                <?php foreach ($doctorData as $doctor): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($doctor['fullname']); ?></td>
                        <td><?php echo htmlspecialchars($doctor['dob']); ?></td>
                        <td><?php echo htmlspecialchars($doctor['gender']); ?></td>
                        <td><?php echo htmlspecialchars($doctor['contact']); ?></td>
                        <td><?php echo htmlspecialchars($doctor['license']); ?></td>
                        <td><?php echo htmlspecialchars($doctor['education']); ?></td>
                        <td><?php echo htmlspecialchars($doctor['experience']); ?></td>
                        <td><?php echo htmlspecialchars($doctor['specialization']); ?></td>
                        <td><?php echo !empty($doctor['photo']) ? htmlspecialchars($doctor['photo']) : 'null'; ?></td>
                        <td><?php echo !empty($doctor['certificate']) ? htmlspecialchars($doctor['certificate']) : 'null'; ?></td>
                        <td><?php echo htmlspecialchars($doctor['rating']); ?></td> <!-- Display Rating -->
                    </tr>
                <?php endforeach; ?>
            </table>
            <button class="book-button btn" onclick="showAppointmentModal('<?php echo htmlspecialchars($doctorData[0]['fullname']); ?>')">Book Appointment</button>
        <?php else: ?>
            <p class="not_found_doctor"><?php echo htmlspecialchars($doctorData); ?></p>
        <?php endif; ?>
    <?php endif; ?>

    <div class="appointment_container" style="margin-top: 20px;">
        <h2 class="appointment_header">Make an Appointment</h2>
        <div class="appointment_btns">
            <button onclick="location.href='find_appointment_by_patient_name.php'" class="btn">
                App by Patient Name
            </button>
            <button onclick="location.href='find_appointmets.php'" class="btn">
                App by Username
            </button>
        </div>
    </div>

    <!-- Appointment Modal -->
    <div id="appointmentModal" class="appointment-modal">
        <div class="appointment-modal-content">
            <span class="close" onclick="closeAppointmentModal()">&times;</span>
            <h2>Book an Appointment</h2>
            <form method="POST">
                <input type="hidden" id="therapistName" name="therapist_name" value="">
                <label for="patient_name">Patient Name:</label>
                <input type="text" id="patient_name" name="patient_name" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>

                <label for="patient_phone">Patient Phone No:</label>
                <input type="text" id="patient_phone" name="patient_phone" value="<?php echo htmlspecialchars($user['contact']); ?>" required>

                <label for="preferred_date">Preferred Date:</label>
                <input type="date" id="preferred_date" name="preferred_date" required>

                <label for="preferred_time">Preferred Time:</label>
                <input type="time" id="preferred_time" name="preferred_time" required>

                <label for="disease">Disease:</label>
                <input type="text" id="disease" name="disease" required>

                <label for="about">About:</label>
                <input type="text" id="about" name="about" required>

                <input type="submit" name="book_appointment" value="Submit">
            </form>
            </form>

<!-- New buttons for demo1.php and demo2.php -->
<div style="margin-top: 20px;">
    <button onclick="location.href='find_appointment_by_patient_name.php'" style="background-color: #4CAF50; color: white; border: none; padding: 10px 20px; cursor: pointer;">
        App by Patient Name
    </button>
    <button onclick="location.href='find_appointmets.php'" style="background-color: #4CAF50; color: white; border: none; padding: 10px 20px; cursor: pointer;">
        App by Username
    </button>
</div>

        </div>
    </div>
    <script>
        document.getElementById('therapists-near-me-btn').addEventListener('click', function() {
            window.location.href = 'map.html';
        });
    </script> 
    <script src="https://cdn.botpress.cloud/webchat/v1/inject.js"></script>
<script src="https://mediafiles.botpress.cloud/f97b401b-aec4-4ca8-8890-5ea3bd43a4ba/webchat/config.js" defer></script>


</body>
</html>