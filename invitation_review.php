<?php
error_reporting(E_ALL ^ E_NOTICE);

ob_start(); // Output Buffering Start

session_start();
$pageTitle = 'Home';
include 'init.php'; 




// التحقق من وجود معرف الدعوة
if (!isset($_GET['id']) || empty($_GET['id'])) {
	die("Invalid invitation ID.");
}

$invitation_id = htmlspecialchars($_GET['id']);


$result = $mysqli->query("SELECT * FROM events_invitations where events_invitations_id = $invitation_id ") or die($mysqli->error);
if ($result->num_rows > 0) {
	while($row = $result->fetch_assoc()) {
		$events_invitations_name=$row["events_invitations_name"];
		$events_invitations_count=$row["events_invitations_count"];
		$events_invitations_eventid=$row["events_invitations_eventid"];
		$events_invitations_type=$row["events_invitations_type"];
		$events_invitations_more=$row["events_invitations_more"];
	}
}

$result = $mysqli->query("SELECT * FROM events_inv_type where events_inv_type_id = $events_invitations_type ") or die($mysqli->error);
if ($result->num_rows > 0) {
	while($row = $result->fetch_assoc()) {
		$events_inv_type_title=$row["events_inv_type_title"];
	}
}


$result = $mysqli->query("SELECT * FROM events where events_id = $events_invitations_eventid ") or die($mysqli->error);
if ($result->num_rows > 0) {
	while($row = $result->fetch_assoc()) {
		$events_name=$row["events_name"];
		$events_desc=$row["events_desc"];
		$events_date=$row["events_date"];
		$events_org_code=$row["events_org_code"];
		$events_for_url=$row["events_for_url"];
		$events_activity=$row["events_activity"];
	}
}


$attended=0;
$result = $mysqli->query("SELECT * FROM events_attendance where events_attendance_invitationid = $invitation_id ") or die($mysqli->error);
if ($result->num_rows > 0) {
	while($row = $result->fetch_assoc()) {
		$attended=1;
	}
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['review_invitation'])) {
	$passcode = $_POST['passcode'];
	$passcode = (int) filter_var($passcode, FILTER_SANITIZE_NUMBER_INT);
	$events_org_code = (int) filter_var($events_org_code, FILTER_SANITIZE_NUMBER_INT);

	$n_of_att = mysqli_real_escape_string($conn, $_POST['n_of_att']);
	$more_inf = mysqli_real_escape_string($conn, $_POST['more_inf']);
	if($more_inf=="" || $more_inf==Null){
		$more_inf="";
	}


	if(isset ($users_id)){



		if($attended==0){

			if($passcode==$events_org_code){
echo 'aaaa';


				$mysqli->query("INSERT INTO events_attendance 
 (
 events_attendance_invitationid,
					events_attendance_moreinf,
					events_attendance_realcount,
					events_attendance_eventid,
					events_attendance_orgid
 )
 VALUES
 (
 '$invitation_id',
					'$more_inf',
					'$n_of_att',
					'$events_invitations_eventid',
					'$users_id'
 )") or die($mysqli->error);
				$_SESSION['MSG_success']='تم تسجيل الحضور بنجاح';
				header('Location: invitation_review.php?id='.$invitation_id); exit;





			}else{
				$_SESSION['MSG_error']='خطأ في كود المنظم!!';
				header('Location: invitation_review.php?id='.$invitation_id); exit;

			}

		}

	}else{

		$_SESSION['MSG_error']='يجب تسجيل الدخول!';

		header('Location: cpanel.php'); exit;

	}


}





if($events_activity==0){
	header('Location: https://wa.me/+201552521511?text=لقد أرسلتم لي دعوة الكترونية لحدث ('.$events_name.') بتاريخ '.$events_date.'، أعتقد أن الحدث تم حذفه');
}
else{
?>

<section class="pt-10 bg-gray-100 sm:pt-16 lg:pt-24">
	<div class="px-4 mx-auto sm:px-6 lg:px-8 max-w-7xl">
		<div class="max-w-2xl mx-auto text-center">
			<p class="text-base tracking-wider text-[#f1d293] uppercase"><?= $events_inv_type_title; ?></p>
			<h2 class="text-3xl font-bold leading-tight text-black sm:text-4xl lg:text-5xl lg:leading-tight"><?= $events_name; ?></h2>
			<h2 class="text-xl mt-2 font-bold leading-tight text-black sm:text-2xl lg:text-3xl lg:leading-tight"><?= $events_date; ?></h2>
		</div>

		<?php if(isset($events_desc)&& $events_desc!=" " && $events_desc!=Null){ ?>
		<div class="mt-12 text-base leading-relaxed text-gray-100 border border-1 bg-black border-b-0 p-4 shadow-t-lg rounded-t-lg">تفاصيل الحدث</div>
		<div class="text-base leading-relaxed text-gray-600 border border-1 p-4 shadow-b-lg rounded-b-lg"><?= $events_desc; ?></div>
		<?php } ?>

		<div class="mt-12 text-base leading-relaxed text-gray-100 border border-1 bg-black border-b-0 p-4 shadow-t-lg rounded-t-lg">تفاصيل إضافية</div>

		<div class="text-base leading-relaxed text-gray-600 border border-1 p-4 shadow-b-lg rounded-b-lg">
			<p class=" ">هذه الدعوة تشمل <?= $events_invitations_count; ?> أفراد.</p>

			<?= $events_invitations_more; ?>
		</div>


		<div class="mt-12 flex-col flex text-center place-items-center border-black justify-center items-center text-base leading-relaxed text-gray-600 border border-1 p-4 rounded-lg">

			<a href="<?= $events_for_url; ?>" class="place-items-center"><img class="max-w-[80px]" src="https://i.postimg.cc/8PPRxPwc/location.png" /></a>
			<a href="<?= $events_for_url; ?>" class="mt-5 items-center justify-center px-4 py-3 text-base font-semibold text-black transition-all duration-200 bg-[#f1d293] border border-transparent rounded-md lg:inline-flex hover:bg-white focus:bg-[#f1f1f1]" role="button">عرض الموقع</a>

		</div>




		<div class="mt-[300px] text-base leading-relaxed text-gray-100 border border-1 bg-black border-b-0 p-4 shadow-t-lg rounded-t-lg">خاص بالمنظمين</div>
		<div class="text-base leading-relaxed text-gray-600 border border-1 p-4 shadow-b-lg rounded-b-lg">

			<?php if($attended==0){ ?>
			<form action="invitation_review.php?id=<?= $invitation_id; ?>" method="POST">
				<div dir="ltr" class="flex space-x-2 justify-center">
					<input type="text" name="passcode" class="w-72 h-12 text-center text-lg border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="●●●●" oninput="this.value = this.value.replace(/\D/g, '').split('').join(' ')" />
				</div>
				<div dir="ltr" class="flex justify-center">
					<input type="number" name="n_of_att" class="w-72 text-center text-lg border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="عدد الحضور" required>
				</div>
				<div dir="ltr" class="flex justify-center">
					<textarea name="more_inf" class="w-72 text-center text-lg border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" rows="4" cols="50" placeholder="تفاصيل إضافية"></textarea>
				</div>
				<div dir="ltr" class="flex mt-2 justify-center">
					<input type="submit" class="w-72 h-12 cursor-pointer inline-flex items-center justify-center px-6 text-base font-semibold text-black transition-all duration-200 bg-[#f1d293] border border-transparent rounded-md lg:inline-flex hover:bg-[#22e203] hover:text-white focus:bg-[#f1f1f1]" value="إرسال" name="review_invitation">
				</div>
			</form>
			<?php }else{ ?>

			تم تسجيل الحضور

			<?php } ?>

		</div>
	</div>
</section>



<?php
	}
include 'includes/templates/footer.php'; 

ob_end_flush();
?>
