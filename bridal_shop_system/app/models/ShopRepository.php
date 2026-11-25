<?php
declare(strict_types=1);

/** Database model for the four existing shop entities. */
final class ShopRepository {
    public function __construct(private PDO $pdo) {}

    public function userByEmail(string $email): ?array {
        $stmt=$this->pdo->prepare('SELECT user_id,username,email,password_hash,role FROM users WHERE email=?');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }
    public function userById(int $id): ?array {
        $stmt=$this->pdo->prepare('SELECT user_id,username,email,password_hash,role FROM users WHERE user_id=?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
    public function allUsers(): array {
        return $this->pdo->query('SELECT user_id,username,email,role,created_at FROM users ORDER BY user_id DESC')->fetchAll();
    }
    public function countManagers(): int {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM users WHERE role='manager'")->fetchColumn();
    }
    public function createUser(string $username,string $email,string $passwordHash,string $role='customer'): int {
        $stmt=$this->pdo->prepare('INSERT INTO users(username,email,password_hash,role) VALUES(?,?,?,?)');
        $stmt->execute([$username,$email,$passwordHash,$role]);
        return (int)$this->pdo->lastInsertId();
    }
    public function updateUser(int $id,string $username,string $email,string $role,?string $passwordHash=null): void {
        if ($passwordHash===null) {
            $stmt=$this->pdo->prepare('UPDATE users SET username=?,email=?,role=? WHERE user_id=?');
            $stmt->execute([$username,$email,$role,$id]);
        } else {
            $stmt=$this->pdo->prepare('UPDATE users SET username=?,email=?,role=?,password_hash=? WHERE user_id=?');
            $stmt->execute([$username,$email,$role,$passwordHash,$id]);
        }
    }
    public function updatePassword(int $id,string $passwordHash): void {
        $stmt=$this->pdo->prepare('UPDATE users SET password_hash=? WHERE user_id=?');
        $stmt->execute([$passwordHash,$id]);
    }
    public function deleteUser(int $id): void {
        $stmt=$this->pdo->prepare('DELETE FROM users WHERE user_id=?');
        $stmt->execute([$id]);
    }

    public function dressSearch(string $term,bool $includeUnavailable=false): array {
        $availability=$includeUnavailable?'':' AND available=1';
        $stmt=$this->pdo->prepare('SELECT dress_id,name,size,color,rental_price,available,description,photo_url FROM dresses WHERE (name LIKE :name_term OR color LIKE :color_term OR size LIKE :size_term)'.$availability.' ORDER BY available DESC,name LIMIT 20');
        $like='%'.$term.'%';
        $stmt->execute(['name_term'=>$like,'color_term'=>$like,'size_term'=>$like]);
        return $stmt->fetchAll();
    }
    public function allDresses(string $sort='name',bool $includeUnavailable=false): array {
        $order=$sort==='availability'?'available DESC,name':'name';
        $availability=$includeUnavailable?'':' WHERE available=1';
        return $this->pdo->query('SELECT * FROM dresses'.$availability.' ORDER BY '.$order)->fetchAll();
    }
    public function featuredDresses(): array {
        return $this->pdo->query('SELECT * FROM dresses WHERE available=1 ORDER BY dress_id DESC LIMIT 5')->fetchAll();
    }
    public function findDress(int $id): ?array {
        $stmt=$this->pdo->prepare('SELECT * FROM dresses WHERE dress_id=?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
    public function dressPhotos(int $id): array {
        $stmt=$this->pdo->prepare('SELECT photo_url FROM dresses WHERE dress_id=?');
        $stmt->execute([$id]);
        $primary=(string)($stmt->fetchColumn() ?: '');
        $stmt=$this->pdo->prepare('SELECT photo_url FROM dress_photos WHERE dress_id=? ORDER BY photo_id');
        $stmt->execute([$id]);
        $photos=array_column($stmt->fetchAll(),'photo_url');
        if ($primary!=='' && !in_array($primary,$photos,true)) array_unshift($photos,$primary);
        return array_values(array_unique($photos));
    }
    public function countAvailableDresses(): int {
        return (int)$this->pdo->query('SELECT COUNT(*) FROM dresses WHERE available=1')->fetchColumn();
    }
    public function saveDress(int $id,array $data,array $photoUrls=[]): bool {
        $this->pdo->beginTransaction();
        try {
        if ($id>0) {
            $stmt=$this->pdo->prepare('SELECT dress_id FROM dresses WHERE dress_id=? FOR UPDATE');
            $stmt->execute([$id]);
            if (!$stmt->fetch()) throw new RuntimeException('This dress could not be found.');
            $stmt=$this->pdo->prepare("SELECT COUNT(*) FROM rentals WHERE dress_id=? AND status IN ('pending','approved')");
            $stmt->execute([$id]);
            if ((int)$stmt->fetchColumn()>0) $data['available']=0;
            if ($data['photo_url']==='' && $photoUrls) $data['photo_url']=$photoUrls[0];
            $stmt=$this->pdo->prepare('UPDATE dresses SET name=?,size=?,color=?,rental_price=?,available=?,description=?,photo_url=? WHERE dress_id=?');
            $stmt->execute([$data['name'],$data['size'],$data['color'],$data['price'],$data['available'],$data['description'],$data['photo_url'],$id]);
            if ($stmt->rowCount()===0 && !$this->findDress($id)) throw new RuntimeException('This dress could not be found.');
            $dressId=$id;
        } else {
            if ($data['photo_url']==='' && $photoUrls) $data['photo_url']=$photoUrls[0];
            $stmt=$this->pdo->prepare('INSERT INTO dresses(name,size,color,rental_price,available,description,photo_url) VALUES(?,?,?,?,?,?,?)');
            $stmt->execute([$data['name'],$data['size'],$data['color'],$data['price'],$data['available'],$data['description'],$data['photo_url']]);
            $dressId=(int)$this->pdo->lastInsertId();
        }
        if ($photoUrls) {
            $stmt=$this->pdo->prepare('INSERT INTO dress_photos(dress_id,photo_url) VALUES(?,?)');
            foreach ($photoUrls as $photoUrl) $stmt->execute([$dressId,$photoUrl]);
        }
        $this->pdo->commit();
        return (int)$data['available']===1;
        } catch (Throwable $ex) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $ex;
        }
    }
    public function activeRentalCount(int $dressId): int {
        $stmt=$this->pdo->prepare("SELECT COUNT(*) FROM rentals WHERE dress_id=? AND status IN ('pending','approved')");
        $stmt->execute([$dressId]);
        return (int)$stmt->fetchColumn();
    }
    public function rentalDressIsAvailable(int $rentalId): bool {
        $stmt=$this->pdo->prepare('SELECT d.available FROM rentals r JOIN dresses d ON d.dress_id=r.dress_id WHERE r.rental_id=?');
        $stmt->execute([$rentalId]);
        return (int)$stmt->fetchColumn()===1;
    }
    public function deleteDress(int $id): void {
        $stmt=$this->pdo->prepare('DELETE FROM dresses WHERE dress_id=?');
        $stmt->execute([$id]);
    }

    public function createRental(int $dressId,int $customerId,string $start,string $end,int $days,bool $termsAccepted): float {
        if (!$termsAccepted) throw new RuntimeException('Accept the payment and rental terms before requesting this dress.');
        $this->pdo->beginTransaction();
        try {
            $stmt=$this->pdo->prepare('SELECT rental_price,available FROM dresses WHERE dress_id=? FOR UPDATE');
            $stmt->execute([$dressId]);
            $dress=$stmt->fetch();
            if (!$dress || !(int)$dress['available']) throw new RuntimeException('This dress is not available.');
            $total=$days*(float)$dress['rental_price'];
            $stmt=$this->pdo->prepare("INSERT INTO rentals(dress_id,customer_id,start_date,end_date,total,status,terms_accepted,payment_status) VALUES(?,?,?,?,?,'pending',1,'awaiting')");
            $stmt->execute([$dressId,$customerId,$start,$end,$total]);
            $stmt=$this->pdo->prepare('UPDATE dresses SET available=0 WHERE dress_id=?');
            $stmt->execute([$dressId]);
            $this->pdo->commit();
            return $total;
        } catch (Throwable $ex) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $ex;
        }
    }
    public function allRentals(bool $manage,int $customerId): array {
        $sql='SELECT r.*,d.name AS dress_name,u.username FROM rentals r JOIN dresses d ON d.dress_id=r.dress_id JOIN users u ON u.user_id=r.customer_id';
        if (!$manage) $sql.=' WHERE r.customer_id=?';
        $sql.=' ORDER BY r.rental_id DESC';
        $stmt=$this->pdo->prepare($sql);
        if ($manage) $stmt->execute(); else $stmt->execute([$customerId]);
        return $stmt->fetchAll();
    }
    public function updateRentalStatus(int $id,string $status,?int $customerId=null): bool {
        $this->pdo->beginTransaction();
        try {
            $sql='SELECT dress_id,payment_status,terms_accepted FROM rentals WHERE rental_id=?';
            $params=[$id];
            if ($customerId!==null) { $sql.=" AND customer_id=? AND status='pending'"; $params[]=$customerId; }
            $sql.=' FOR UPDATE';
            $stmt=$this->pdo->prepare($sql);
            $stmt->execute($params);
            $rental=$stmt->fetch();
            if (!$rental) { $this->pdo->rollBack(); return false; }
            if ($status==='approved' && ((int)$rental['terms_accepted']!==1 || $rental['payment_status']!=='confirmed')) { $this->pdo->rollBack(); throw new RuntimeException('Accept the terms and confirm payment before approving this rental.'); }
            $stmt=$this->pdo->prepare('UPDATE rentals SET status=? WHERE rental_id=?');
            $stmt->execute([$status,$id]);
            $this->syncDressAvailability((int)$rental['dress_id']);
            $this->pdo->commit();
            return true;
        } catch (Throwable $ex) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $ex;
        }
    }
    public function updateRentalPaymentStatus(int $id,string $status): bool {
        if (!in_array($status,['confirmed','released'],true)) throw new RuntimeException('Invalid payment action.');
        $this->pdo->beginTransaction();
        try {
            $stmt=$this->pdo->prepare('SELECT dress_id,terms_accepted,payment_status FROM rentals WHERE rental_id=? FOR UPDATE');
            $stmt->execute([$id]); $rental=$stmt->fetch();
            if (!$rental || (int)$rental['terms_accepted']!==1) { $this->pdo->rollBack(); throw new RuntimeException('Customer terms acceptance is missing.'); }
            if ($status==='released' && $rental['payment_status']!=='confirmed') { $this->pdo->rollBack(); throw new RuntimeException('Confirm payment before releasing this rental.'); }
            $stmt=$this->pdo->prepare('UPDATE rentals SET payment_status=?,status=IF(?="released","approved",status) WHERE rental_id=?');
            $stmt->execute([$status,$status,$id]);
            $this->syncDressAvailability((int)$rental['dress_id']);
            $this->pdo->commit();
            return true;
        } catch (Throwable $ex) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $ex; }
    }
    public function deleteRental(int $id): bool {
        $this->pdo->beginTransaction();
        try {
            $stmt=$this->pdo->prepare('SELECT dress_id FROM rentals WHERE rental_id=? FOR UPDATE');
            $stmt->execute([$id]);
            $rental=$stmt->fetch();
            if (!$rental) { $this->pdo->rollBack(); return false; }
            $stmt=$this->pdo->prepare('DELETE FROM rentals WHERE rental_id=?');
            $stmt->execute([$id]);
            $this->syncDressAvailability((int)$rental['dress_id']);
            $this->pdo->commit();
            return true;
        } catch (Throwable $ex) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $ex;
        }
    }
    private function syncDressAvailability(int $dressId): void {
        $stmt=$this->pdo->prepare('SELECT dress_id FROM dresses WHERE dress_id=? FOR UPDATE');
        $stmt->execute([$dressId]);
        if (!$stmt->fetch()) return;
        $stmt=$this->pdo->prepare("SELECT COUNT(*) FROM rentals WHERE dress_id=? AND status IN ('pending','approved')");
        $stmt->execute([$dressId]);
        $available=(int)$stmt->fetchColumn()===0?1:0;
        $stmt=$this->pdo->prepare('UPDATE dresses SET available=? WHERE dress_id=?');
        $stmt->execute([$available,$dressId]);
    }

    public function allFittings(bool $manage,int $customerId): array {
        $sql='SELECT f.*,r.customer_id,r.dress_id,d.name AS dress_name,u.username FROM fittings f JOIN rentals r ON r.rental_id=f.rental_id JOIN dresses d ON d.dress_id=r.dress_id JOIN users u ON u.user_id=r.customer_id';
        if (!$manage) $sql.=' WHERE r.customer_id=?';
        $sql.=' ORDER BY f.fitting_date';
        $stmt=$this->pdo->prepare($sql);
        if ($manage) $stmt->execute(); else $stmt->execute([$customerId]);
        return $stmt->fetchAll();
    }
    public function rentalOptions(bool $manage,int $customerId): array {
        $sql='SELECT r.rental_id,d.name FROM rentals r JOIN dresses d ON d.dress_id=r.dress_id';
        if (!$manage) $sql.=' WHERE r.customer_id=?';
        $sql.=' ORDER BY r.rental_id DESC';
        $stmt=$this->pdo->prepare($sql);
        if ($manage) $stmt->execute(); else $stmt->execute([$customerId]);
        return $stmt->fetchAll();
    }
    public function accessibleRental(int $rentalId,bool $manage,int $customerId): bool {
        $sql='SELECT rental_id FROM rentals WHERE rental_id=?'.($manage?'':' AND customer_id=?');
        $stmt=$this->pdo->prepare($sql);
        if ($manage) $stmt->execute([$rentalId]); else $stmt->execute([$rentalId,$customerId]);
        return (bool)$stmt->fetch();
    }
    public function saveFitting(int $id,int $rentalId,string $date,string $notes,int $completed,bool $manage): void {
        if ($id>0 && $manage) {
            $stmt=$this->pdo->prepare('UPDATE fittings SET rental_id=?,fitting_date=?,notes=?,completed=? WHERE fitting_id=?');
            $stmt->execute([$rentalId,$date,$notes,$completed,$id]);
        } else {
            $stmt=$this->pdo->prepare('INSERT INTO fittings(rental_id,fitting_date,notes,completed) VALUES(?,?,?,?)');
            $stmt->execute([$rentalId,$date,$notes,$manage?$completed:0]);
        }
    }
    public function deleteFitting(int $id): void {
        $stmt=$this->pdo->prepare('DELETE FROM fittings WHERE fitting_id=?');
        $stmt->execute([$id]);
    }
    public function dashboardStats(): array {
        return [
            'users'=>(int)$this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'dresses'=>(int)$this->pdo->query('SELECT COUNT(*) FROM dresses')->fetchColumn(),
            'rentals'=>(int)$this->pdo->query('SELECT COUNT(*) FROM rentals')->fetchColumn(),
            'pending'=>(int)$this->pdo->query("SELECT COUNT(*) FROM rentals WHERE status='pending'")->fetchColumn()
        ];
    }
}
