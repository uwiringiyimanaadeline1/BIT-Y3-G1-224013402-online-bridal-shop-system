<?php
declare(strict_types=1);
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
$isHttps=isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off';
session_set_cookie_params(['httponly'=>true,'secure'=>$isHttps,'samesite'=>'Lax']);
session_start();
require __DIR__ . '/config/database.php';
require __DIR__ . '/app/helpers.php';
require __DIR__ . '/app/models/ShopRepository.php';
$shop = new ShopRepository($pdo);

if (is_logged_in()) {
    $currentUser=$shop->userById((int)$_SESSION['user']['user_id']);
    if (!$currentUser) {
        unset($_SESSION['user']);
        session_regenerate_id(true);
    } else {
        unset($currentUser['password_hash']);
        $_SESSION['user']=$currentUser;
    }
}

$page = $_GET['page'] ?? 'home';
$method = $_SERVER['REQUEST_METHOD'];

if ($page === 'api-search') {
    header('Content-Type: application/json; charset=utf-8');
    $term = trim((string)($_GET['q'] ?? ''));
    if (text_length($term) > 80) {
        http_response_code(400);
        echo json_encode(['error'=>'Search term is too long.']);
        exit;
    }
    echo json_encode($shop->dressSearch($term,can_manage_shop()), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($page === 'logout' && $method === 'POST') {
    verify_csrf();
    $_SESSION = [];
    session_destroy();
    redirect('home.php');
}

if ($page === 'register' && $method === 'POST') {
    verify_csrf();
    $username = trim((string)($_POST['username'] ?? ''));
    $email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $password = (string)($_POST['password'] ?? '');
    $returnDressId=(int)($_POST['return_dress_id'] ?? 0);
    $errors = [];
    if (text_length($username) < 2 || text_length($username) > 100) $errors[]='Username must be 2–100 characters.';
    if (!$email) $errors[]='Enter a valid email address.';
    if (text_length($password) < 4 || text_length($password)>255) $errors[]='Password must contain 4–255 characters.';
    if (!$errors) {
        try {
            $shop->createUser($username,(string)$email,password_hash($password,PASSWORD_DEFAULT));
            flash('Account created. You can now log in.');
            redirect('login.php'.($returnDressId>0?'?dress_id='.$returnDressId:''));
        } catch (PDOException $ex) {
            $errors[]='That email address is already registered.';
        }
    }
}

if ($page === 'login' && $method === 'POST') {
    verify_csrf();
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $returnDressId=(int)($_POST['return_dress_id'] ?? 0);
    $user=$shop->userByEmail($email);
    if ($user && password_verify($password,$user['password_hash'])) {
        session_regenerate_id(true);
        unset($user['password_hash']);
        $_SESSION['user']=$user;
        flash('Welcome back, ' . $user['username'] . '!');
        if (can_manage_shop()) redirect('admin.php');
        redirect($returnDressId>0?'dress.php?id='.$returnDressId:'home.php');
    }
    $errors=['Incorrect email or password.'];
}

if ($page === 'password-change' && $method === 'POST') {
    require_login(); verify_csrf();
    $currentPassword=(string)($_POST['current_password'] ?? '');
    $newPassword=(string)($_POST['new_password'] ?? '');
    $confirmPassword=(string)($_POST['confirm_password'] ?? '');
    $account=$shop->userById((int)$_SESSION['user']['user_id']);
    $errors=[];
    if (!$account || !password_verify($currentPassword,$account['password_hash'])) $errors[]='Your current password is incorrect.';
    if (text_length($newPassword)<4 || text_length($newPassword)>255) $errors[]='New password must contain 4–255 characters.';
    if ($newPassword!==$confirmPassword) $errors[]='The new passwords do not match.';
    if (!$errors) {
        $shop->updatePassword((int)$_SESSION['user']['user_id'],password_hash($newPassword,PASSWORD_DEFAULT));
        flash('Password changed successfully.');
        redirect('home.php');
    }
}

if ($page === 'user-save' && $method === 'POST') {
    require_manager(); verify_csrf();
    $id=(int)($_POST['user_id'] ?? 0);
    $username=trim((string)($_POST['username'] ?? ''));
    $email=filter_var(trim((string)($_POST['email'] ?? '')),FILTER_VALIDATE_EMAIL);
    $role=(string)($_POST['role'] ?? 'customer');
    $password=(string)($_POST['password'] ?? '');
    $target=$id>0?$shop->userById($id):null;
    $errors=[];
    if ($id>0 && !$target) $errors[]='That account could not be found.';
    if ($id>0 && $id===(int)$_SESSION['user']['user_id']) $errors[]='Edit your own password using Change password; another manager can update your profile.';
    if (text_length($username)<2 || text_length($username)>100) $errors[]='Username must be 2–100 characters.';
    if (!$email || text_length((string)$email)>150) $errors[]='Enter a valid email address up to 150 characters.';
    if (!in_array($role,['manager','customer'],true)) $errors[]='Choose a valid account role.';
    if (($id===0 || $password!=='') && (text_length($password)<4 || text_length($password)>255)) $errors[]='Password must contain 4–255 characters.';
    if ($target && $target['role']==='manager' && $role!=='manager' && $shop->countManagers()<=1) $errors[]='The shop must keep at least one manager.';
    if (!$errors) {
        try {
            $passwordHash=$password!==''?password_hash($password,PASSWORD_DEFAULT):null;
            if ($id>0) $shop->updateUser($id,$username,(string)$email,$role,$passwordHash);
            else $shop->createUser($username,(string)$email,(string)$passwordHash,$role);
            flash($id>0?'Account updated.':'Account created.');
            redirect('users.php');
        } catch (PDOException $ex) { $errors[]='That email address is already registered.'; }
    }
    $userErrors=$errors;
    $page='users';
    if ($id>0) $_GET['edit']=(string)$id;
}

if ($page === 'user-delete' && $method === 'POST') {
    require_manager(); verify_csrf();
    $id=(int)($_POST['user_id'] ?? 0);
    $target=$shop->userById($id);
    if (!$target) flash('Account not found.','error');
    elseif ($id===(int)$_SESSION['user']['user_id']) flash('You cannot delete your own signed-in account.','error');
    elseif ($target['role']==='manager' && $shop->countManagers()<=1) flash('The shop must keep at least one manager.','error');
    else {
        try { $shop->deleteUser($id); flash('Account deleted.'); }
        catch (PDOException $ex) { flash('This account has rental records and cannot be deleted.','error'); }
    }
    redirect('users.php');
}

if ($page === 'dress-save' && $method === 'POST') {
    require_manager();
    $contentLength=(int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    $postLimit=trim((string)ini_get('post_max_size'));
    $postLimitBytes=(float)$postLimit;
    $postLimitUnit=strtolower(substr($postLimit,-1));
    if ($postLimitUnit==='g') $postLimitBytes*=1024*1024*1024;
    elseif ($postLimitUnit==='m') $postLimitBytes*=1024*1024;
    elseif ($postLimitUnit==='k') $postLimitBytes*=1024;
    if ($contentLength>0 && $postLimitBytes>0 && $contentLength>$postLimitBytes && empty($_POST)) {
        flash('The selected photos exceed the server upload limit. Reduce the image sizes or increase PHP post_max_size.','error');
        redirect('dresses.php');
    }
    verify_csrf();
    $id=(int)($_POST['dress_id'] ?? 0);
    $name=trim((string)($_POST['name'] ?? ''));
    $size=trim((string)($_POST['size'] ?? ''));
    $color=trim((string)($_POST['color'] ?? ''));
    $price=filter_var($_POST['rental_price'] ?? null,FILTER_VALIDATE_FLOAT);
    $available=isset($_POST['available']) ? 1 : 0;
    $description=trim((string)($_POST['description'] ?? ''));
    $photoUrl=trim((string)($_POST['photo_url'] ?? ''));
    $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $pendingUploads=[];
    $totalUploadBytes=0;
    $uploads=$_FILES['dress_photos'] ?? null;
    if (is_array($uploads) && is_array($uploads['name'] ?? null)) {
        foreach ($uploads['name'] as $index=>$uploadName) {
            if ($uploadName==='') continue;
            $error=(int)($uploads['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            $tmpName=(string)($uploads['tmp_name'][$index] ?? '');
            $sizeBytes=(int)($uploads['size'][$index] ?? 0);
            $mime=$error===UPLOAD_ERR_OK && $tmpName!=='' ? (new finfo(FILEINFO_MIME_TYPE))->file($tmpName) : '';
            if ($error!==UPLOAD_ERR_OK || $sizeBytes>5*1024*1024 || !isset($extensions[$mime])) {
                flash('Choose JPG, PNG or WebP photos no larger than 5 MB each.','error');
                redirect('dresses.php');
            }
            $totalUploadBytes+=$sizeBytes;
            if ($totalUploadBytes>30*1024*1024) {
                flash('Keep the combined dress photo upload under 30 MB.','error');
                redirect('dresses.php');
            }
            $pendingUploads[]=['tmp_name'=>$tmpName,'extension'=>$extensions[$mime]];
        }
    }
    if (count($pendingUploads)>10) {
        flash('Upload no more than 10 dress photos at a time.','error');
        redirect('dresses.php');
    }
    if ($name==='' || $size==='' || $color==='' || $price===false || $price<=0 || text_length($name)>120 || text_length($size)>20 || text_length($color)>50 || text_length($description)>5000 || ($photoUrl!=='' && !preg_match('~^(https://|assets/images/clothes/[a-f0-9]{32}\\.(jpg|png|webp)$)~i',$photoUrl))) {
        flash('Enter a valid dress name, size, color and positive rental price.','error');
        redirect('dresses.php');
    }
    $uploadDir=__DIR__.'/assets/images/clothes';
    $photoUrls=[];
    $movedPhotoPaths=[];
    foreach ($pendingUploads as $upload) {
        $filename=bin2hex(random_bytes(16)).'.'.$upload['extension'];
        $destination=$uploadDir.'/'.$filename;
        if (!move_uploaded_file($upload['tmp_name'],$destination)) {
            foreach ($movedPhotoPaths as $movedPath) unlink($movedPath);
            flash('Photo could not be saved. Check the clothing photos folder permissions.','error');
            redirect('dresses.php');
        }
        $movedPhotoPaths[]=$destination;
        $photoUrls[]='assets/images/clothes/'.$filename;
    }
    try {
        $savedAvailable=$shop->saveDress($id,['name'=>$name,'size'=>$size,'color'=>$color,'price'=>$price,'available'=>$available,'description'=>$description,'photo_url'=>$photoUrl],$photoUrls);
    } catch (Throwable $ex) {
        foreach ($movedPhotoPaths as $movedPath) unlink($movedPath);
        throw $ex;
    }
    if ($available===1 && !$savedAvailable) {
        flash('Dress details were saved, but it remains unavailable while a rental is pending or approved.','warning');
    } else {
        flash($id>0?'Dress updated successfully.':'Dress added successfully.');
    }
    redirect('dresses.php');
}
if ($page === 'dress-delete' && $method === 'POST') {
    require_manager(); verify_csrf();
    try {
        $shop->deleteDress((int)($_POST['dress_id'] ?? 0));
        flash('Dress deleted.');
    } catch (PDOException $ex) { flash('This dress has rental records and cannot be deleted.','error'); }
    redirect('dresses.php');
}

if ($page === 'rental-save' && $method === 'POST') {
    require_login(); verify_csrf();
    $termsAccepted=($_POST['terms_accepted'] ?? '')==='yes';
    if (!$termsAccepted) { flash('Accept the payment and rental terms before submitting.','error'); redirect('my-rentals.php'); }
    $dressId=(int)($_POST['dress_id'] ?? 0);
    $customerId=(int)$_SESSION['user']['user_id'];
    if (can_manage_shop()) {
        $customer=$shop->userById((int)($_POST['customer_id'] ?? 0));
        if (!$customer || $customer['role']!=='customer') {
            flash('Choose a valid customer for this rental.','error');
            redirect('my-rentals.php');
        }
        $customerId=(int)$customer['user_id'];
    }
    $start=(string)($_POST['start_date'] ?? '');
    $end=(string)($_POST['end_date'] ?? '');
    $startDate=valid_date($start);
    $endDate=valid_date($end);
    if (!$startDate || !$endDate || $endDate < $startDate || $startDate < new DateTime('today')) {
        flash('Choose valid rental dates. The end date must not be before the start date.','error');
        redirect('my-rentals.php');
    }
    try {
        $days=(int)$startDate->diff($endDate)->days+1;
        $total=$shop->createRental($dressId,$customerId,$start,$end,$days,$termsAccepted);
        flash('Rental request submitted. Total: RWF ' . number_format($total,0));
    } catch (Throwable $ex) {
        flash($ex instanceof RuntimeException ? $ex->getMessage() : 'Could not submit rental request.','error');
    }
    redirect('my-rentals.php');
}

if ($page === 'rent' && $method === 'POST') {
    require_login(); verify_csrf();
    $termsAccepted=($_POST['terms_accepted'] ?? '')==='yes';
    if (!$termsAccepted) { flash('Please accept the payment and rental terms before continuing.','error'); redirect('dresses.php?id='.(int)($_POST['dress_id'] ?? 0)); }
    $dressId=(int)($_POST['dress_id'] ?? 0);
    $start=(string)($_POST['start_date'] ?? '');
    $end=(string)($_POST['end_date'] ?? '');
    $startDate=valid_date($start);
    $endDate=valid_date($end);
    if (!$startDate || !$endDate || $endDate < $startDate || $startDate < new DateTime('today')) {
        flash('Choose valid rental dates. The end date must not be before the start date.','error');
        redirect('dresses.php');
    }
    try {
        $days=(int)$startDate->diff($endDate)->days+1;
        $total=$shop->createRental($dressId,(int)$_SESSION['user']['user_id'],$start,$end,$days,$termsAccepted);
        flash('Rental request submitted with your terms acceptance. The manager will confirm payment and release the rental. Total: RWF ' . number_format($total,0));
    } catch (Throwable $ex) {
        flash($ex instanceof RuntimeException ? $ex->getMessage() : 'Could not submit rental request.','error');
    }
    redirect('my-rentals.php');
}

if ($page === 'rental-payment-update' && $method !== 'POST') {
    redirect('my-rentals.php');
}

if ($page === 'rental-payment-update' && $method === 'POST') {
    require_manager(); verify_csrf();
    $id=(int)($_POST['rental_id'] ?? 0);
    $status=(string)($_POST['payment_status'] ?? '');
    try {
        $updated=$shop->updateRentalPaymentStatus($id,$status);
        flash($status==='released'?'Payment confirmed and rental released.':'Payment confirmed. Review it again to release the rental.');
    } catch (Throwable $ex) {
        flash($ex instanceof RuntimeException?$ex->getMessage():'Could not update payment status.','error');
    }
    redirect('my-rentals.php');
}

if ($page === 'rental-update' && $method === 'POST') {
    require_login(); verify_csrf();
    $id=(int)($_POST['rental_id'] ?? 0);
    $status=(string)($_POST['status'] ?? '');
    $allowed=['pending','approved','returned','cancelled'];
    if (!in_array($status,$allowed,true)) { flash('Invalid rental status.','error'); redirect('my-rentals.php'); }
    if (!can_manage_shop() && $status!=='cancelled') { http_response_code(403); exit('Not allowed.'); }
    $updated=$shop->updateRentalStatus($id,$status,can_manage_shop()?null:(int)$_SESSION['user']['user_id']);
    if (!$updated) { flash('Rental not found or no longer available to update.','error'); redirect('my-rentals.php'); }
    if (in_array($status,['returned','cancelled'],true) && !$shop->rentalDressIsAvailable($id)) {
        flash('Rental status updated. The dress remains unavailable because another rental is still pending or approved.','warning');
    } elseif (in_array($status,['returned','cancelled'],true)) {
        flash('Rental status updated. The dress is now available.','success');
    } else {
        flash('Rental status updated. Dress availability has been refreshed.');
    }
    redirect('my-rentals.php');
}
if ($page === 'rental-delete' && $method === 'POST') {
    require_manager(); verify_csrf();
    try {
        $deleted=$shop->deleteRental((int)($_POST['rental_id'] ?? 0));
    } catch (PDOException $ex) {
        if ((int)($ex->errorInfo[1] ?? 0)!==1451) throw $ex;
        flash('This rental cannot be deleted because it has fitting appointments. Delete its fitting appointments first.','error');
        redirect('my-rentals.php');
    }
    if (!$deleted) {
        flash('This rental could not be found.','error');
    } else {
        flash('Rental deleted.');
    }
    redirect('my-rentals.php');
}

if ($page === 'fitting-save' && $method === 'POST') {
    require_login(); verify_csrf();
    $id=(int)($_POST['fitting_id'] ?? 0);
    if (!can_manage_shop() && $id>0) {
        http_response_code(403);
        exit('Customers can only create fitting appointments.');
    }
    if (can_manage_shop() && $id===0) {
        http_response_code(403);
        exit('Only customers can create fitting appointments.');
    }
    $rentalId=(int)($_POST['rental_id'] ?? 0);
    $date=(string)($_POST['fitting_date'] ?? '');
    $notes=trim((string)($_POST['notes'] ?? ''));
    $completed=isset($_POST['completed'])?1:0;
    $fittingDate=valid_date($date);
    if (!$shop->accessibleRental($rentalId,can_manage_shop(),(int)$_SESSION['user']['user_id']) || !$fittingDate || $fittingDate<new DateTime('today') || text_length($notes)>5000) {
        flash('Select one of your valid rentals and a fitting date.','error'); redirect('fittings.php');
    }
    $shop->saveFitting($id,$rentalId,$date,$notes,$completed,can_manage_shop());
    flash('Fitting appointment saved.');
    redirect('fittings.php');
}
if ($page === 'fitting-delete' && $method === 'POST') {
    require_manager(); verify_csrf();
    $shop->deleteFitting((int)($_POST['fitting_id'] ?? 0));
    flash('Fitting appointment deleted.');
    redirect('fittings.php');
}

if (in_array($page,['my-rentals','fittings','password-change'],true)) require_login();
if ($page==='admin') require_manager();
if ($page==='users') require_manager();

$errors=$errors ?? [];
$pageTitle=$page==='admin'?'Manager dashboard':ucwords(str_replace('-',' ',$page));
require __DIR__ . '/app/views/header.php';

switch ($page) {
case 'home':
    $count=$shop->countAvailableDresses();
    $featured=$shop->featuredDresses();
    ?>
    <section class="home-carousel" id="homeCarousel" aria-label="Featured wedding dresses">
      <?php foreach($featured as $index=>$dress): ?>
        <a class="carousel-slide <?= $index===0?'active':'' ?>" href="dress.php?id=<?= (int)$dress['dress_id'] ?>" <?= $index===0?'':'hidden' ?>>
          <?php if(!empty($dress['photo_url'])): ?><img src="<?= e($dress['photo_url']) ?>" alt="<?= e($dress['name']) ?>" <?= $index===0?'':'loading="lazy"' ?>><?php else: ?><span class="carousel-placeholder" aria-hidden="true"></span><?php endif; ?>
          <span class="carousel-caption"><strong><?= e($dress['name']) ?></strong><span>View dress <i class="bi bi-arrow-right" aria-hidden="true"></i></span></span>
        </a>
      <?php endforeach; ?>
      <?php if(!$featured): ?>
        <div class="carousel-empty">
          <img class="carousel-empty-image" src="assets/images/clothes/download.jpg" alt="Bride wearing a wedding dress outdoors">
          <div class="carousel-empty-content">
            <strong>Find the dress for your special day</strong>
            <p>Our collection is being prepared. Please check back soon.</p>
            <a href="dresses.php">Explore the collection <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
          </div>
        </div>
      <?php endif; ?>
      <?php if(count($featured)>1): ?>
        <button class="carousel-control previous" type="button" aria-label="Previous featured dress"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
        <button class="carousel-control next" type="button" aria-label="Next featured dress"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
        <div class="carousel-dots" aria-label="Choose a featured dress">
          <?php foreach($featured as $index=>$dress): ?><button type="button" class="<?= $index===0?'active':'' ?>" aria-label="Show featured dress <?= $index+1 ?>" data-slide="<?= $index ?>"></button><?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
    <section class="stats"><div><strong><?= $count ?></strong><span>Available dresses</span></div><div><strong>4</strong><span>Easy steps: browse, rent, fit, celebrate</span></div><div><strong>1</strong><span>Special day, beautifully prepared</span></div></section>
    <section class="section-heading"><div><span class="eyebrow">THE COLLECTION</span><h2>Find a dress to rent</h2><p>Explore available gowns and view every detail before booking.</p></div><a href="dresses.php">View collection →</a></section>
    <?php
    if ($featured) {
        echo '<div class="dress-grid">'; require __DIR__.'/app/views/dress-cards.php'; echo '</div>';
    } else {
        echo '<p class="empty-state">There are no dresses available to rent right now. Please check back soon.</p>';
    }
    break;

case 'register':
    $returnDressId=(int)($_GET['dress_id'] ?? $_POST['return_dress_id'] ?? 0);
    ?>
    <section class="auth-card"><span class="eyebrow">JOIN OUR BRIDAL COMMUNITY</span><h1>Create your account</h1>
    <?php foreach($errors as $error): ?><div class="alert error"><?= e($error) ?></div><?php endforeach; ?>
    <form method="post" class="form-grid">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <?php if($returnDressId>0): ?><input type="hidden" name="return_dress_id" value="<?= $returnDressId ?>"><?php endif; ?>
      <label>Username<input name="username" required minlength="2" maxlength="100" value="<?= old('username') ?>"></label>
      <label>Email<input type="email" name="email" required value="<?= old('email') ?>"></label>
      <label>Password (4–255 characters)<input type="password" name="password" required minlength="4" maxlength="255"></label>
      <button class="button full" type="submit">Create account</button>
    </form><p class="muted">Already registered? <a href="login.php<?= $returnDressId>0?'?dress_id='.$returnDressId:'' ?>">Log in</a></p></section>
    <?php break;

case 'login':
    $returnDressId=(int)($_GET['dress_id'] ?? $_POST['return_dress_id'] ?? 0);
    ?>
    <section class="auth-card"><span class="eyebrow">WELCOME BACK</span><h1>Log in</h1>
    <?php foreach($errors as $error): ?><div class="alert error"><?= e($error) ?></div><?php endforeach; ?>
    <form method="post" action="login.php" class="form-grid">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <?php if($returnDressId>0): ?><input type="hidden" name="return_dress_id" value="<?= $returnDressId ?>"><?php endif; ?>
      <label>Email<input type="email" name="email" required value="<?= old('email') ?>"></label>
      <label>Password<input type="password" name="password" required></label>
      <button class="button full" type="submit">Log in</button>
    </form><p class="muted">New here? <a href="register.php<?= $returnDressId>0?'?dress_id='.$returnDressId:'' ?>">Sign up</a></p></section>
    <?php break;

case 'password-change':
    ?>
    <section class="auth-card"><span class="eyebrow">ACCOUNT SECURITY</span><h1>Change password</h1>
    <?php foreach($errors as $error): ?><div class="alert error"><?= e($error) ?></div><?php endforeach; ?>
    <form method="post" action="password-change.php" class="form-grid">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label>
      <label>New password (4–255 characters)<input type="password" name="new_password" required minlength="4" maxlength="255" autocomplete="new-password"></label>
      <label>Confirm new password<input type="password" name="confirm_password" required minlength="4" maxlength="255" autocomplete="new-password"></label>
      <button class="button full" type="submit">Save new password</button>
    </form></section>
    <?php break;

case 'dress':
    $dress=$shop->findDress((int)($_GET['id'] ?? 0));
    if (!$dress) {
        http_response_code(404);
        ?><section class="auth-card"><h1>Dress not found</h1><a href="dresses.php">Return to dresses</a></section><?php
        break;
    }
    $photos=$shop->dressPhotos((int)$dress['dress_id']);
    ?>
    <section class="product-detail">
      <div class="product-gallery">
        <div class="product-main-image" id="productMainImage">
          <?php if($photos): ?><img src="<?= e($photos[0]) ?>" alt="<?= e($dress['name']) ?> wedding dress" id="selectedDressImage"><?php else: ?><div class="photo-placeholder"><strong><?= e($dress['name']) ?></strong><span>Dress photos coming soon</span></div><?php endif; ?>
        </div>
        <?php if(count($photos)>1): ?><div class="product-thumbnails" aria-label="More dress photos">
          <?php foreach($photos as $index=>$photo): ?><button type="button" class="<?= $index===0?'active':'' ?>" data-photo="<?= e($photo) ?>" aria-label="View dress photo <?= $index+1 ?>"><img src="<?= e($photo) ?>" alt="" loading="lazy"></button><?php endforeach; ?>
        </div><?php endif; ?>
      </div>
      <div class="product-details">
        <a class="back-link" href="dresses.php"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to dresses</a>
        <span class="eyebrow"><?= e($dress['color']) ?> · Size <?= e($dress['size']) ?></span>
        <h1><?= e($dress['name']) ?></h1>
        <p class="product-description"><?= e($dress['description'] ?? '') ?></p>
        <div class="product-price"><?= number_format((float)$dress['rental_price']) ?> <small>RWF / day</small></div>
        <p><span class="<?= $dress['available']?'available':'unavailable' ?>"><?= $dress['available']?'Available to rent':'Currently unavailable' ?></span></p>
        <?php if($dress['available'] && !is_logged_in()): ?>
          <a class="button full" href="login.php?dress_id=<?= (int)$dress['dress_id'] ?>">Log in to rent this dress</a>
          <p class="muted">New to the shop? <a href="register.php?dress_id=<?= (int)$dress['dress_id'] ?>">Create an account</a></p>
        <?php elseif($dress['available'] && !can_manage_shop()): ?>
          <form method="post" action="rent.php" class="rent-form product-rent-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="dress_id" value="<?= (int)$dress['dress_id'] ?>">
            <label>From<input type="date" name="start_date" min="<?= date('Y-m-d') ?>" required></label>
            <label>To<input type="date" name="end_date" min="<?= date('Y-m-d') ?>" required></label>
            <fieldset class="terms-box"><legend>Payment and rental terms</legend><p>You agree to pay the displayed rental price. Your request remains pending until the manager confirms payment and releases the rental.</p><label class="inline-check"><input type="checkbox" name="terms_accepted" value="yes" required> I have read and accept these terms.</label></fieldset>
            <button class="button full" type="submit">Request rental</button>
          </form>
        <?php elseif(can_manage_shop()): ?>
          <a class="button full" href="dresses.php?edit=<?= (int)$dress['dress_id'] ?>">Manage this dress</a>
        <?php endif; ?>
      </div>
    </section>
    <?php break;

case 'dresses':
    $edit=null;
    if (isset($_GET['edit']) && can_manage_shop()) {
        $edit=$shop->findDress((int)$_GET['edit']);
    }
    $editActiveRentals=$edit?$shop->activeRentalCount((int)$edit['dress_id']):0;
    $searchTerm=trim((string)($_GET['q'] ?? ''));
    $dresses=$searchTerm!==''?$shop->dressSearch($searchTerm,can_manage_shop()):$shop->allDresses('availability',can_manage_shop());
    $editPhotos=$edit?$shop->dressPhotos((int)$edit['dress_id']):[];
    ?>
    <div class="page-heading-row">
      <section class="page-intro"><span class="eyebrow">THE COLLECTION</span><h1>Wedding dresses</h1><p>Search the collection and find a gown that feels like you.</p></section>
      <?php if(can_manage_shop()): ?><button class="button create-panel-toggle" type="button" data-expand-toggle="dressCreatePanel" data-open-label="<?= $edit?'Close edit form':'Close form' ?>" data-close-label="<?= $edit?'Edit dress':'New Dress' ?>" aria-expanded="<?= $edit?'true':'false' ?>" aria-controls="dressCreatePanel"><i class="bi bi-plus-lg" aria-hidden="true"></i><span data-toggle-label><?= $edit?'Close edit form':'New Dress' ?></span></button><?php endif; ?>
    </div>
    <?php if (can_manage_shop()): ?>
    <div class="create-panel" id="dressCreatePanel" <?= $edit?'':'hidden' ?>><section class="panel"><h2><?= $edit?'Edit dress':'Add a dress' ?></h2>
      <form method="post" enctype="multipart/form-data" action="dress-save.php" class="form-grid two-col">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="dress_id" value="<?= e((string)($edit['dress_id']??'')) ?>">
      <label>Dress name<input name="name" required maxlength="120" value="<?= e($edit['name']??'') ?>"></label>
      <label>Size<input name="size" required value="<?= e($edit['size']??'') ?>"></label>
      <label>Color<input name="color" required value="<?= e($edit['color']??'') ?>"></label>
      <label>Rental price (RWF)<input type="number" name="rental_price" min="1" step="0.01" required value="<?= e((string)($edit['rental_price']??'')) ?>"></label>
      <label class="check-label"><input type="checkbox" name="available" <?= !isset($edit['available']) || $edit['available']?'checked':'' ?>> Available</label>
      <?php if($editActiveRentals>0): ?><p class="muted wide">This dress cannot be marked available while <?= $editActiveRentals ?> pending or approved rental<?= $editActiveRentals===1?'':'s' ?> remain<?= $editActiveRentals===1?'s':'' ?>.</p><?php endif; ?>
      <label class="wide">Description<textarea name="description" rows="2"><?= e($edit['description']??'') ?></textarea></label>
      <label class="wide photo-upload-label">Dress photos (JPG, PNG or WebP; up to 5 MB each, 30 MB total)<input id="dressPhotos" type="file" name="dress_photos[]" accept="image/jpeg,image/png,image/webp" multiple><input type="url" name="photo_url" placeholder="Or paste a secure https photo link" value="<?= e($edit['photo_url']??'') ?>"></label>
      <div id="dressPhotoPreview" class="dress-photo-preview wide" aria-live="polite">
        <?php if($editPhotos): foreach($editPhotos as $index=>$photo): ?><figure class="photo-preview-item"><img src="<?= e($photo) ?>" alt="Saved dress photo <?= $index+1 ?>"><figcaption>Saved photo <?= $index+1 ?></figcaption></figure><?php endforeach; else: ?><span class="photo-preview-empty">Choose photos to preview them here.</span><?php endif; ?>
      </div>
      <button class="button" type="submit"><?= $edit?'Update dress':'Add dress' ?></button>
      <?php if($edit): ?><a class="button secondary" href="dresses.php">Cancel edit</a><?php endif; ?>
      </form></section></div>
    <?php endif; ?>
    <div class="collection-toolbar">
      <label class="dress-search"><i class="bi bi-search" aria-hidden="true"></i><input id="dressSearch" type="search" value="<?= e($searchTerm) ?>" placeholder="Search by dress, color or size..." aria-label="Search dresses"></label>
      <?php if(can_manage_shop()): ?>
      <div class="view-switch" role="group" aria-label="Dress display style">
        <button type="button" class="active" data-dress-view="table" aria-pressed="true"><i class="bi bi-list-ul" aria-hidden="true"></i> Table</button>
        <button type="button" data-dress-view="cards" aria-pressed="false"><i class="bi bi-grid" aria-hidden="true"></i> Cards</button>
      </div>
      <?php endif; ?>
    </div>
    <div id="searchResults" class="dress-results <?= can_manage_shop()?'':'show-cards' ?>" data-can-manage="<?= can_manage_shop()?'true':'false' ?>" data-csrf-token="<?= e(csrf_token()) ?>">
      <?php if(can_manage_shop()): ?>
      <div class="table-scroll dress-table-view">
        <table class="data-table dress-list-table">
          <thead><tr><th>Dress</th><th>Size</th><th>Color</th><th>Rental price</th><th>Availability</th><th>Actions</th></tr></thead>
          <tbody id="dressTableBody">
          <?php foreach($dresses as $d): ?>
            <tr>
              <td class="dress-table-name"><?php if(!empty($d['photo_url'])): ?><img src="<?= e($d['photo_url']) ?>" alt="" loading="lazy"><?php else: ?><span class="table-photo-placeholder"><i class="bi bi-image" aria-hidden="true"></i></span><?php endif; ?><span><strong><?= e($d['name']) ?></strong><small><?= e($d['description'] ?? '') ?></small></span></td>
              <td><?= e($d['size']) ?></td><td><?= e($d['color']) ?></td>
              <td class="dress-table-price"><?= number_format((float)$d['rental_price']) ?> <small>RWF</small></td>
              <td><span class="availability-badge <?= $d['available']?'is-available':'is-unavailable' ?>"><?= $d['available']?'Available':'Rented' ?></span></td>
              <td><div class="table-actions">
                <a class="table-action" href="dress.php?id=<?= (int)$d['dress_id'] ?>">View</a>
                <?php if(can_manage_shop()): ?>
                  <a class="table-action" href="dresses.php?edit=<?= (int)$d['dress_id'] ?>">Edit</a>
                  <form method="post" action="dress-delete.php" onsubmit="return confirm('Delete this dress?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="dress_id" value="<?= (int)$d['dress_id'] ?>"><button class="table-action danger" type="submit">Delete</button></form>
                <?php endif; ?>
              </div></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
      <div id="dressCardView" class="dress-grid dress-card-view">
        <?php $items=$dresses; $managerListing=can_manage_shop(); require __DIR__.'/app/views/dress-cards.php'; ?>
      </div>
      <p id="noDressResults" class="empty-state" <?= $dresses?'hidden':'' ?>>No dresses found.</p>
    </div>
    <?php
    break;

case 'my-rentals':
    $rentals=$shop->allRentals(can_manage_shop(),(int)$_SESSION['user']['user_id']);
    $availableDresses=$shop->allDresses('name',false);
    $rentalCustomers=can_manage_shop()?array_values(array_filter($shop->allUsers(),static fn(array $user): bool=>$user['role']==='customer')):[];
    ?>
    <div class="page-heading-row">
      <section class="page-intro"><span class="eyebrow">YOUR BOOKINGS</span><h1><?= can_manage_shop()?'Rental management':'My rentals' ?></h1><p>Track booking requests and rental status.</p></section>
      <button class="button create-panel-toggle" type="button" data-expand-toggle="rentalCreatePanel" data-open-label="Close form" data-close-label="New Rental" aria-expanded="false" aria-controls="rentalCreatePanel"><i class="bi bi-plus-lg" aria-hidden="true"></i><span data-toggle-label>New Rental</span></button>
    </div>
    <?php if(can_manage_shop()): ?><section class="panel"><h2>Payment confirmation and release</h2><p>Confirm payment first, then release the purchase to approve the rental.</p><div class="table-scroll"><table><thead><tr><th>Customer and dress</th><th>Amount</th><th>Terms</th><th>Payment status and action</th></tr></thead><tbody><?php foreach($rentals as $paymentRental): ?><tr><td><?= e($paymentRental['username']) ?><small><?= e($paymentRental['dress_name']) ?></small></td><td><?= number_format((float)$paymentRental['total']) ?> RWF</td><td><?= (int)($paymentRental['terms_accepted']??0)===1?'Accepted':'Not accepted' ?></td><td><?= e(ucfirst((string)($paymentRental['payment_status']??'awaiting'))) ?><?php if((int)($paymentRental['terms_accepted']??0)===1 && ($paymentRental['payment_status']??'awaiting')==='awaiting'): ?><form method="post" action="rental-payment-update.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="rental_id" value="<?= (int)$paymentRental['rental_id'] ?>"><input type="hidden" name="payment_status" value="confirmed"><button class="small-button" type="submit">Confirm payment</button></form><?php elseif(($paymentRental['payment_status']??'')==='confirmed' && $paymentRental['status']==='pending'): ?><form method="post" action="rental-payment-update.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="rental_id" value="<?= (int)$paymentRental['rental_id'] ?>"><input type="hidden" name="payment_status" value="released"><button class="small-button" type="submit">Confirm and release</button></form><?php endif; ?></td></tr><?php endforeach; if(!$rentals): ?><tr><td colspan="4">No rental payments to review.</td></tr><?php endif; ?></tbody></table></div></section><?php endif; ?>
    <div class="create-panel" id="rentalCreatePanel" hidden><section class="panel"><h2>Request a rental</h2>
      <?php if($availableDresses && (!can_manage_shop() || $rentalCustomers)): ?>
      <form method="post" action="rental-save.php" class="form-grid two-col">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <?php if(can_manage_shop()): ?><label>Customer<select name="customer_id" required><option value="">Choose customer</option><?php foreach($rentalCustomers as $customer): ?><option value="<?= (int)$customer['user_id'] ?>"><?= e($customer['username']) ?> — <?= e($customer['email']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
        <label>Dress<select name="dress_id" required><option value="">Choose available dress</option><?php foreach($availableDresses as $availableDress): ?><option value="<?= (int)$availableDress['dress_id'] ?>"><?= e($availableDress['name']) ?> — <?= number_format((float)$availableDress['rental_price']) ?> RWF/day</option><?php endforeach; ?></select></label>
        <label>From<input type="date" name="start_date" min="<?= date('Y-m-d') ?>" required></label>
        <label>To<input type="date" name="end_date" min="<?= date('Y-m-d') ?>" required></label>
        <label class="inline-check wide"><input type="checkbox" name="terms_accepted" value="yes" required> Customer has accepted the payment and rental terms.</label>
        <button class="button" type="submit">Request rental</button>
      </form>
      <?php elseif(!$availableDresses): ?><p class="empty-state">There are no dresses available to rent right now.</p>
      <?php else: ?><p class="empty-state">Create a customer account before adding a rental.</p><?php endif; ?>
    </section></div>
    <section class="panel"><div class="table-scroll"><table><thead><tr><th>Dress / Customer</th><th>Dates</th><th>Total</th><th>Status</th><th>Update status</th><?php if(can_manage_shop()): ?><th>Delete</th><?php endif; ?></tr></thead><tbody>
    <?php foreach($rentals as $r): ?><tr><td><?= e($r['dress_name']) ?><?php if(can_manage_shop()): ?><small><?= e($r['username']) ?></small><?php endif; ?></td><td><?= e($r['start_date']) ?> to <?= e($r['end_date']) ?></td><td><?= number_format((float)$r['total']) ?> RWF</td><td><span class="status status-<?= e($r['status']) ?>"><?= e($r['status']) ?></span><small>Payment: <?= e(ucfirst((string)($r['payment_status']??'awaiting'))) ?> · Terms <?= (int)($r['terms_accepted']??0)===1?'accepted':'pending' ?></small></td><td>
    <?php if(can_manage_shop() || ($r['status']==='pending' && (int)$r['customer_id']===(int)$_SESSION['user']['user_id'])): ?>
    <form method="post" action="rental-update.php" class="action-form rental-status-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="rental_id" value="<?= (int)$r['rental_id'] ?>">
    <select name="status" aria-label="Rental #<?= (int)$r['rental_id'] ?> status"><?php foreach((can_manage_shop()?['pending','approved','returned','cancelled']:['cancelled']) as $s): ?><option value="<?= $s ?>" <?= $r['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select><button class="small-button" type="submit">Save</button></form>
    <?php else: ?><span class="muted">—</span><?php endif; ?>
    </td><?php if(can_manage_shop()): ?><td><form method="post" action="rental-delete.php" onsubmit="return confirm('Delete this rental?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="rental_id" value="<?= (int)$r['rental_id'] ?>"><button class="text-danger">Delete</button></form></td><?php endif; ?></tr><?php endforeach; if(!$rentals): ?><tr><td colspan="<?= can_manage_shop()?6:5 ?>">No rentals found yet.</td></tr><?php endif; ?>
    </tbody></table></div></section>
    <?php break;

case 'fittings':
    $fittings=$shop->allFittings(can_manage_shop(),(int)$_SESSION['user']['user_id']);
    $rentalOptions=can_manage_shop()?[]:$shop->rentalOptions(false,(int)$_SESSION['user']['user_id']);
    ?>
    <div class="page-heading-row">
      <section class="page-intro"><span class="eyebrow">THE PERFECT FIT</span><h1>Fitting appointments</h1><?php if(!can_manage_shop()): ?><p>Book a time to try on your rented dress. Choose a rental and date; notes are optional.</p><?php endif; ?></section>
      <?php if(!can_manage_shop()): ?><button class="button create-panel-toggle" type="button" data-expand-toggle="fittingCreatePanel" data-open-label="Close form" data-close-label="New Fitting" aria-expanded="false" aria-controls="fittingCreatePanel"><i class="bi bi-plus-lg" aria-hidden="true"></i><span data-toggle-label>New Fitting</span></button><?php endif; ?>
    </div>
    <?php if(!can_manage_shop()): ?><div class="create-panel" id="fittingCreatePanel" hidden><section class="panel"><h2>Book a fitting</h2>
    <?php if($rentalOptions): ?><form method="post" action="fitting-save.php" class="form-grid two-col">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <label>Rental<select name="rental_id" required><option value="">Choose rental</option><?php foreach($rentalOptions as $ro): ?><option value="<?= (int)$ro['rental_id'] ?>">#<?= (int)$ro['rental_id'] ?> — <?= e($ro['name']) ?></option><?php endforeach; ?></select></label>
    <label>Fitting date<input type="date" name="fitting_date" min="<?= date('Y-m-d') ?>" required></label>
    <label class="wide">Notes<textarea name="notes" rows="2" placeholder="Measurements or adjustments to discuss..."></textarea></label>
    <button class="button" type="submit">Save fitting</button></form><?php else: ?><p class="empty-state">You need a rental before booking a fitting.</p><?php endif; ?></section></div><?php endif; ?>
    <section class="panel"><h2>Fitting schedule</h2><div class="table-scroll"><table><thead><tr><th>Customer / Dress</th><th>Date</th><th>Notes</th><th>Completed</th><?php if(can_manage_shop()): ?><th>Update</th><th>Delete</th><?php endif; ?></tr></thead><tbody>
    <?php foreach($fittings as $f): ?><tr><td><?= e($f['username']) ?> <small><?= e($f['dress_name']) ?></small></td><td><?= e($f['fitting_date']) ?></td><td><?= e($f['notes']) ?></td><td><span class="status <?= $f['completed']?'status-completed':'status-incomplete' ?>"><?= $f['completed']?'Completed':'Pending' ?></span></td><?php if(can_manage_shop()): ?><td><form method="post" action="fitting-save.php" class="action-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="fitting_id" value="<?= (int)$f['fitting_id'] ?>"><input type="hidden" name="rental_id" value="<?= (int)$f['rental_id'] ?>"><input type="hidden" name="fitting_date" value="<?= e($f['fitting_date']) ?>"><input type="hidden" name="notes" value="<?= e($f['notes']) ?>"><label class="inline-check"><input type="checkbox" name="completed" <?= $f['completed']?'checked':'' ?>> Completed</label><button class="small-button">Save</button></form></td><td><form method="post" action="fitting-delete.php" onsubmit="return confirm('Delete this fitting appointment?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="fitting_id" value="<?= (int)$f['fitting_id'] ?>"><button class="text-danger">Delete</button></form></td><?php endif; ?></tr><?php endforeach; if(!$fittings): ?><tr><td colspan="<?= can_manage_shop()?6:4 ?>">No fitting appointments yet.</td></tr><?php endif; ?>
    </tbody></table></div></section>
    <?php break;

case 'admin':
    $stats=$shop->dashboardStats();
    ?>
    <section class="page-intro"><span class="eyebrow">CONTROL CENTRE</span><h1>Manager dashboard</h1><p>Manage the shop's dresses, rentals and fittings.</p></section>
    <div class="stats admin-stats"><?php foreach($stats as $label=>$value): ?><div><strong><?= $value ?></strong><span><?= ucfirst($label) ?></span></div><?php endforeach; ?></div>
    <div class="quick-links"><a href="users.php">Manage users →</a><a href="dresses.php">Manage dresses →</a><a href="my-rentals.php">Manage rentals →</a><a href="fittings.php">Manage fittings →</a></div>
    <?php break;

case 'users':
    $editId=(int)($_GET['edit'] ?? $userEditId ?? 0);
    $edit=$editId>0?$shop->userById($editId):null;
    if ($edit) unset($edit['password_hash']);
    $userErrors=$userErrors ?? [];
    $userFormOpen=(bool)$edit || (bool)$userErrors;
    $users=$shop->allUsers();
    ?>
    <div class="page-heading-row">
      <section class="page-intro"><span class="eyebrow">MANAGER ACCESS</span><h1>Manage user accounts</h1><p>Create accounts, update roles and remove accounts that have no rental history.</p></section>
      <button class="button create-panel-toggle" type="button" data-expand-toggle="userCreatePanel" data-open-label="<?= $edit?'Close edit form':'Close form' ?>" data-close-label="<?= $edit?'Edit account':'New User' ?>" aria-expanded="<?= $userFormOpen?'true':'false' ?>" aria-controls="userCreatePanel"><i class="bi bi-plus-lg" aria-hidden="true"></i><span data-toggle-label><?= $userFormOpen?($edit?'Close edit form':'Close form'):($edit?'Edit account':'New User') ?></span></button>
    </div>
    <?php foreach($userErrors as $error): ?><div class="alert error"><?= e($error) ?></div><?php endforeach; ?>
    <div class="create-panel" id="userCreatePanel" <?= $userFormOpen?'':'hidden' ?>><section class="panel"><h2><?= $edit?'Edit account':'Create an account' ?></h2>
      <form method="post" action="user-save.php" class="form-grid two-col">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="user_id" value="<?= (int)($edit['user_id'] ?? 0) ?>">
      <label>Username<input name="username" required minlength="2" maxlength="100" value="<?= e((string)($_POST['username'] ?? $edit['username'] ?? '')) ?>"></label>
      <label>Email<input type="email" name="email" required maxlength="150" value="<?= e((string)($_POST['email'] ?? $edit['email'] ?? '')) ?>"></label>
      <label>Role<select name="role" required><?php foreach(['customer'=>'Customer','manager'=>'Manager'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= (string)($_POST['role'] ?? $edit['role'] ?? 'customer')===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
      <label><?= $edit?'New password (leave blank to keep current)':'Temporary password (4–255 characters)' ?><input type="password" name="password" maxlength="255" <?= $edit?'':'required minlength="4"' ?> autocomplete="new-password"></label>
      <button class="button" type="submit"><?= $edit?'Update account':'Create account' ?></button>
      <?php if($edit): ?><a class="button secondary" href="users.php">Cancel edit</a><?php endif; ?>
      </form></section></div>
    <section class="panel"><h2>Accounts</h2><div class="table-scroll"><table><thead><tr><th>Username</th><th>Email</th><th>Role</th><th>Created</th><th>Actions</th></tr></thead><tbody>
    <?php foreach($users as $u): ?><tr><td><?= e($u['username']) ?></td><td><?= e($u['email']) ?></td><td><?= e(ucfirst($u['role'])) ?></td><td><?= e($u['created_at']) ?></td><td class="actions"><?php if((int)$u['user_id']!==(int)$_SESSION['user']['user_id']): ?><a href="users.php?edit=<?= (int)$u['user_id'] ?>">Edit</a><form method="post" action="user-delete.php" onsubmit="return confirm('Delete this account?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>"><button class="text-danger">Delete</button></form><?php else: ?><span class="muted">Signed-in account</span><?php endif; ?></td></tr><?php endforeach; ?>
    <?php if(!$users): ?><tr><td colspan="5">No accounts found.</td></tr><?php endif; ?>
    </tbody></table></div></section>
    <?php break;

default:
    http_response_code(404); ?><section class="auth-card"><h1>Page not found</h1><a href="home.php">Return home</a></section><?php
}
require __DIR__ . '/app/views/footer.php';
