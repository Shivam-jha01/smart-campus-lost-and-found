<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
if (file_exists(__DIR__ . '/mail_config.php')) require_once __DIR__ . '/mail_config.php';
$action = $_GET['action'] ?? '';
$in = json_decode(file_get_contents('php://input'), true) ?: [];
function ok($d=[]) { echo json_encode(['ok'=>true] + $d); exit; }
function fail($m,$c=400) { http_response_code($c); echo json_encode(['ok'=>false,'message'=>$m]); exit; }
function f($k,$d='') { global $in; return trim((string)($in[$k] ?? $d)); }
function user_row($u){ return ['id'=>(int)$u['id'],'name'=>$u['name'],'email'=>$u['email'],'uid'=>$u['uid']??'','dept'=>$u['dept']??'','role'=>$u['role']??'','status'=>$u['status']??'active','isAdmin'=>(bool)($u['is_admin']??$u['isAdmin']??0)]; }
function admin_row($u){ return ['id'=>(int)$u['id'],'name'=>$u['name'],'email'=>$u['email'],'uid'=>$u['uid']??'','dept'=>$u['dept']??'','role'=>$u['role']??'admin','status'=>$u['status']??'active','isAdmin'=>true]; }
function need_user(){ if(empty($_SESSION['user'])) fail('Please login first',401); return $_SESSION['user']; }
function need_admin(){ $u=need_user(); if(empty($u['isAdmin'])) fail('Admin access required',403); return $u; }
function log_action($a,$d,$uid=null){ global $pdo; $pdo->prepare('INSERT INTO audit_logs(user_id,action,description) VALUES(?,?,?)')->execute([$uid,$a,$d]); }
function ensure_user_table(){ global $pdo; $pdo->exec('CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, email VARCHAR(120) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, uid VARCHAR(50) NOT NULL UNIQUE, dept VARCHAR(100) NOT NULL, role VARCHAR(30) NOT NULL, status ENUM("active","inactive") NOT NULL DEFAULT "active", is_admin TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB'); }
function ensure_user_uid_unique_index(){ global $pdo; $st=$pdo->query('SHOW INDEX FROM users WHERE Key_name="uid_unique"'); if($st->fetch()) return; $dupes=(int)$pdo->query('SELECT COUNT(*) FROM (SELECT uid FROM users GROUP BY uid HAVING COUNT(*)>1) d')->fetchColumn(); if($dupes===0) $pdo->exec('ALTER TABLE users ADD UNIQUE KEY uid_unique (uid)'); }
function ensure_admin_table(){ global $pdo; $pdo->exec('CREATE TABLE IF NOT EXISTS admins (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, email VARCHAR(120) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, uid VARCHAR(50) NOT NULL UNIQUE, dept VARCHAR(100) NOT NULL, role VARCHAR(30) NOT NULL DEFAULT "admin", status ENUM("active","inactive") NOT NULL DEFAULT "active", created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB'); $count=(int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn(); if($count===0){ $pdo->prepare('INSERT INTO admins(name,email,password,uid,dept,role,status) VALUES(?,?,?,?,?,"admin","active")')->execute(['Admin User','admin@demo.com','admin123','ADMIN001','Administration']); } }
function ensure_audit_logs_table(){ global $pdo; $pdo->exec('CREATE TABLE IF NOT EXISTS audit_logs (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, action VARCHAR(50) NOT NULL, description TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB'); }
function ensure_email_table(){ global $pdo; $pdo->exec('CREATE TABLE IF NOT EXISTS email_notifications (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, recipient_email VARCHAR(120) NOT NULL, subject VARCHAR(180) NOT NULL, message TEXT NOT NULL, status VARCHAR(30) NOT NULL DEFAULT "queued", created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB'); }
function ensure_item_tables(){
  global $pdo;
  $pdo->exec('CREATE TABLE IF NOT EXISTS lost_items (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, user_name VARCHAR(100) NOT NULL, item_name VARCHAR(150) NOT NULL, category VARCHAR(50) NOT NULL, description TEXT, keywords VARCHAR(255), image_path VARCHAR(255), date_lost DATE NOT NULL, location VARCHAR(150) NOT NULL, status ENUM("Active","Resolved") NOT NULL DEFAULT "Active", created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB');
  $pdo->exec('CREATE TABLE IF NOT EXISTS found_items (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, user_name VARCHAR(100) NOT NULL, item_name VARCHAR(150) NOT NULL, category VARCHAR(50) NOT NULL, description TEXT, keywords VARCHAR(255), image_path VARCHAR(255), date_found DATE NOT NULL, location VARCHAR(150) NOT NULL, status ENUM("Available","Claimed") NOT NULL DEFAULT "Available", created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB');
  $pdo->exec('CREATE TABLE IF NOT EXISTS claims (id INT AUTO_INCREMENT PRIMARY KEY, item_id INT NOT NULL, lost_item_id INT NULL, item_name VARCHAR(150) NOT NULL, user_id INT NOT NULL, user_name VARCHAR(100) NOT NULL, details TEXT NOT NULL, image_path VARCHAR(255), status ENUM("Pending","Approved","Rejected") NOT NULL DEFAULT "Pending", created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (item_id) REFERENCES found_items(id) ON DELETE CASCADE, FOREIGN KEY (lost_item_id) REFERENCES lost_items(id) ON DELETE SET NULL, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB');
}
function ensure_claim_lost_item_column(){
  global $pdo;
  $st=$pdo->prepare('SHOW COLUMNS FROM claims LIKE "lost_item_id"');
  $st->execute();
  if(!$st->fetch()) $pdo->exec('ALTER TABLE claims ADD lost_item_id INT NULL AFTER item_id');
}
function ensure_match_notifications_table(){
  global $pdo;
  $pdo->exec('CREATE TABLE IF NOT EXISTS item_match_notifications (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, lost_item_id INT NOT NULL, found_item_id INT NOT NULL, match_score INT NOT NULL DEFAULT 0, match_reason VARCHAR(255), is_read TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY unique_match (user_id,lost_item_id,found_item_id), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (lost_item_id) REFERENCES lost_items(id) ON DELETE CASCADE, FOREIGN KEY (found_item_id) REFERENCES found_items(id) ON DELETE CASCADE) ENGINE=InnoDB');
}
function smtp_read($socket){ $data=''; while($line=fgets($socket,515)){ $data.=$line; if(isset($line[3]) && $line[3]==' ') break; } return $data; }
function smtp_cmd($socket,$cmd,$codes){ fwrite($socket,$cmd."\r\n"); $res=smtp_read($socket); foreach((array)$codes as $code){ if(strpos($res,(string)$code)===0) return $res; } throw new Exception('SMTP error: '.$res); }
function smtp_send_mail($to,$subject,$message){
  if(!defined('SMTP_ENABLED') || !SMTP_ENABLED) return false;
  $host=SMTP_HOST; $port=SMTP_PORT; $user=SMTP_USERNAME; $pass=SMTP_PASSWORD;
  $from=SMTP_FROM_EMAIL; $fromName=SMTP_FROM_NAME;
  $socket=stream_socket_client("tcp://$host:$port", $errno, $errstr, 20);
  if(!$socket) throw new Exception("SMTP connection failed: $errstr");
  smtp_read($socket);
  smtp_cmd($socket,'EHLO localhost',250);
  smtp_cmd($socket,'STARTTLS',220);
  stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT);
  smtp_cmd($socket,'EHLO localhost',250);
  smtp_cmd($socket,'AUTH LOGIN',334);
  smtp_cmd($socket,base64_encode($user),334);
  smtp_cmd($socket,base64_encode($pass),235);
  smtp_cmd($socket,'MAIL FROM:<'.$from.'>',250);
  smtp_cmd($socket,'RCPT TO:<'.$to.'>',[250,251]);
  smtp_cmd($socket,'DATA',354);
  $headers=[];
  $headers[]='From: '.$fromName.' <'.$from.'>';
  $headers[]='To: <'.$to.'>';
  $headers[]='Subject: '.$subject;
  $headers[]='MIME-Version: 1.0';
  $headers[]='Content-Type: text/plain; charset=UTF-8';
  fwrite($socket,implode("\r\n",$headers)."\r\n\r\n".$message."\r\n.\r\n");
  $res=smtp_read($socket); if(strpos($res,'250')!==0) throw new Exception('SMTP send failed: '.$res);
  smtp_cmd($socket,'QUIT',221); fclose($socket); return true;
}
function notify_email($userId,$email,$subject,$message){ global $pdo; if(!$email) return false; ensure_email_table(); $sent=false; $status='queued'; try{ $sent=smtp_send_mail($email,$subject,$message); $status=$sent?'sent':'queued'; }catch(Exception $e){ $status='failed'; } $st=$pdo->prepare('INSERT INTO email_notifications(user_id,recipient_email,subject,message,status) VALUES(?,?,?,?,?)'); $st->execute([$userId,$email,$subject,$message,$status]); return $sent; }
function notify_user_id($userId,$subject,$message){ global $pdo; $st=$pdo->prepare('SELECT email FROM users WHERE id=?'); $st->execute([$userId]); $email=$st->fetchColumn(); return notify_email($userId,$email,$subject,$message); }
function item_row($r){ return ['id'=>(int)$r['id'],'userId'=>(int)$r['user_id'],'userName'=>$r['user_name'],'itemName'=>$r['item_name'],'category'=>$r['category'],'description'=>$r['description']??'','keywords'=>$r['keywords']??'','dateLost'=>$r['date_lost']??null,'dateFound'=>$r['date_found']??null,'location'=>$r['location'],'status'=>$r['status'],'timestamp'=>$r['created_at'],'imagePath'=>$r['image_path']??'']; }
function claim_row($r){
  $claimImage=$r['claim_image_path']??($r['image_path']??'');
  $itemImage=$r['item_image_path']??'';
  return ['id'=>(int)$r['id'],'itemId'=>(int)$r['item_id'],'lostItemId'=>empty($r['lost_item_id'])?null:(int)$r['lost_item_id'],'itemName'=>$r['item_name'],'lostItemName'=>$r['lost_item_name']??'','userId'=>(int)$r['user_id'],'userName'=>$r['claim_user_name']??$r['user_name'],'details'=>$r['details'],'status'=>$r['status'],'timestamp'=>$r['created_at'],'imagePath'=>$claimImage,'claimImagePath'=>$claimImage,'itemImagePath'=>$itemImage];
}
function log_row($r){ return ['id'=>(int)$r['id'],'userId'=>$r['user_id']===null?null:(int)$r['user_id'],'action'=>$r['action'],'desc'=>$r['description'],'timestamp'=>$r['created_at']]; }
function match_tokens($text){
  $text=strtolower((string)$text);
  $parts=preg_split('/[^a-z0-9]+/', $text);
  $stop=['the'=>1,'and'=>1,'for'=>1,'with'=>1,'from'=>1,'this'=>1,'that'=>1,'item'=>1,'lost'=>1,'found'=>1,'near'=>1,'area'=>1,'block'=>1,'floor'=>1,'room'=>1,'has'=>1,'have'=>1,'inside'=>1];
  $out=[];
  foreach($parts as $p){ if(strlen($p)>=3 && empty($stop[$p])) $out[$p]=1; }
  return array_keys($out);
}
function token_overlap($a,$b){ return count(array_intersect(match_tokens($a), match_tokens($b))); }
function item_match_score($lost,$found){
  $score=0; $reasons=[];
  if(strcasecmp(trim($lost['category']),trim($found['category']))===0){ $score+=35; $reasons[]='same category'; }
  $lostText=implode(' ',[$lost['item_name']??'',$lost['description']??'',$lost['keywords']??'']);
  $foundText=implode(' ',[$found['item_name']??'',$found['description']??'',$found['keywords']??'']);
  $wordHits=token_overlap($lostText,$foundText);
  if($wordHits>0){ $score+=min(35,$wordHits*10); $reasons[]=$wordHits.' keyword match'.($wordHits>1?'es':''); }
  $lostName=strtolower(trim($lost['item_name']??'')); $foundName=strtolower(trim($found['item_name']??''));
  if($lostName && $foundName && (strpos($lostName,$foundName)!==false || strpos($foundName,$lostName)!==false)){ $score+=15; $reasons[]='similar item name'; }
  $lostLoc=strtolower(trim($lost['location']??'')); $foundLoc=strtolower(trim($found['location']??''));
  if($lostLoc && $foundLoc){
    if($lostLoc===$foundLoc || strpos($lostLoc,$foundLoc)!==false || strpos($foundLoc,$lostLoc)!==false){ $score+=25; $reasons[]='same location'; }
    else {
      $locHits=token_overlap($lostLoc,$foundLoc);
      if($locHits>0){ $score+=min(20,$locHits*10); $reasons[]='nearby location'; }
    }
  }
  return ['score'=>$score,'reasons'=>$reasons];
}
function possible_found_matches($lost,$excludeUserId=null,$limit=5){
  global $pdo;
  $st=$pdo->prepare('SELECT * FROM found_items WHERE status="Available" AND (? IS NULL OR user_id<>?) ORDER BY id DESC LIMIT 100');
  $st->execute([$excludeUserId,$excludeUserId]);
  $matches=[];
  foreach($st->fetchAll() as $found){
    $m=item_match_score($lost,$found);
    if($m['score']>=35){
      $row=item_row($found);
      $row['matchScore']=$m['score'];
      $row['matchReason']=implode(', ',$m['reasons']);
      $row['lostItemId']=(int)($lost['id']??0);
      $row['lostItemName']=$lost['item_name']??'';
      $matches[]=$row;
    }
  }
  usort($matches,function($a,$b){ return $b['matchScore']<=>$a['matchScore']; });
  return array_slice($matches,0,$limit);
}
function possible_lost_matches($found,$excludeUserId=null,$limit=20){
  global $pdo;
  $st=$pdo->prepare('SELECT * FROM lost_items WHERE status="Active" AND (? IS NULL OR user_id<>?) ORDER BY id DESC LIMIT 100');
  $st->execute([$excludeUserId,$excludeUserId]);
  $matches=[];
  foreach($st->fetchAll() as $lost){
    $m=item_match_score($lost,$found);
    if($m['score']>=35){
      $row=item_row($lost);
      $row['matchScore']=$m['score'];
      $row['matchReason']=implode(', ',$m['reasons']);
      $matches[]=$row;
    }
  }
  usort($matches,function($a,$b){ return $b['matchScore']<=>$a['matchScore']; });
  return array_slice($matches,0,$limit);
}
function save_match_notification($userId,$lostItemId,$foundItemId,$score,$reason){
  global $pdo;
  ensure_match_notifications_table();
  $st=$pdo->prepare('INSERT INTO item_match_notifications(user_id,lost_item_id,found_item_id,match_score,match_reason) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE match_score=VALUES(match_score), match_reason=VALUES(match_reason), created_at=CURRENT_TIMESTAMP');
  $st->execute([$userId,$lostItemId,$foundItemId,$score,$reason]);
}
function user_match_notifications($userId,$limit=10){
  global $pdo;
  ensure_match_notifications_table();
  $st=$pdo->prepare('SELECT n.*, l.item_name AS lost_item_name, f.item_name AS found_item_name, f.location AS found_location, f.image_path AS item_image_path, f.date_found, f.status AS found_status, l.status AS lost_status FROM item_match_notifications n JOIN lost_items l ON l.id=n.lost_item_id JOIN found_items f ON f.id=n.found_item_id WHERE n.user_id=? AND l.status="Active" AND f.status="Available" ORDER BY n.id DESC LIMIT '.$limit);
  $st->execute([$userId]);
  $messages=[];
  foreach($st->fetchAll() as $r){
    $messages[]=[
      'itemName'=>$r['found_item_name'],
      'message'=>'Your item is available in claim section. You can claim it.',
      'location'=>$r['found_location']??'',
      'timestamp'=>$r['created_at'],
      'imagePath'=>$r['item_image_path']??'',
      'itemId'=>(int)$r['found_item_id'],
      'matchScore'=>(int)$r['match_score'],
      'matchReason'=>$r['match_reason']??'',
      'lostItemId'=>(int)$r['lost_item_id'],
      'lostItemName'=>$r['lost_item_name'],
      'dateFound'=>$r['date_found']??''
    ];
  }
  return $messages;
}
function ensure_image_columns(){ global $pdo; foreach(['lost_items','found_items'] as $table){ $st=$pdo->prepare('SHOW COLUMNS FROM '.$table.' LIKE "image_path"'); $st->execute(); if(!$st->fetch()){ $pdo->exec('ALTER TABLE '.$table.' ADD image_path VARCHAR(255) NULL AFTER keywords'); } } $st=$pdo->prepare('SHOW COLUMNS FROM claims LIKE "image_path"'); $st->execute(); if(!$st->fetch()){ $pdo->exec('ALTER TABLE claims ADD image_path VARCHAR(255) NULL AFTER details'); } }
function save_item_image($data,$prefix){ if(!$data) return ''; if(!preg_match('/^data:image\/(png|jpeg|jpg|gif|webp);base64,/', $data, $m)) fail('Invalid image format'); $ext=$m[1]==='jpeg'?'jpg':$m[1]; $raw=base64_decode(substr($data, strpos($data, ',')+1)); if($raw===false) fail('Could not read image'); if(strlen($raw)>2*1024*1024) fail('Image must be smaller than 2 MB'); $dir=__DIR__.'/uploads'; if(!is_dir($dir)) mkdir($dir,0777,true); $name=$prefix.'_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext; file_put_contents($dir.'/'.$name,$raw); return 'uploads/'.$name; }
try {
ensure_user_table();
ensure_user_uid_unique_index();
ensure_admin_table();
ensure_audit_logs_table();
ensure_item_tables();
ensure_claim_lost_item_column();
ensure_match_notifications_table();
ensure_image_columns();
if($action==='session') ok(['user'=>$_SESSION['user']??null]);
if($action==='login'){
  $email=f('email'); $pass=f('password'); $isAdmin=!empty($in['isAdmin']);
  if(!$email||!$pass) fail('Please enter email and password');
  if($isAdmin){
    $st=$pdo->prepare('SELECT * FROM admins WHERE email=? LIMIT 1'); $st->execute([$email]); $u=$st->fetch();
    if(!$u) fail('Invalid email or password',401);
    if(!(password_verify($pass,$u['password']) || hash_equals($u['password'],$pass))) fail('Invalid email or password',401);
    if($u['status']!=='active') fail('This admin account is inactive',403);
    $_SESSION['user']=admin_row($u); log_action('LOGIN','Admin '.$u['name'].' logged in'); ok(['user'=>$_SESSION['user']]);
  }
  $st=$pdo->prepare('SELECT * FROM users WHERE email=? AND is_admin=0 LIMIT 1'); $st->execute([$email]); $u=$st->fetch();
  if(!$u) fail('Invalid email or password',401);
  if(!(password_verify($pass,$u['password']) || hash_equals($u['password'],$pass))) fail('Invalid email or password',401);
  if($u['status']!=='active') fail('This account is inactive',403);
  $_SESSION['user']=user_row($u); log_action('LOGIN','User '.$u['name'].' logged in',$u['id']); ok(['user'=>$_SESSION['user']]);
}
if($action==='register'){
  $name=f('name'); $email=f('email'); $uid=f('uid'); $dept=f('dept'); $role=f('role','student'); $pass=f('password');
  if(!$name||!$email||!$uid||!$dept||!$pass) fail('Please fill all required fields');
  if(!filter_var($email,FILTER_VALIDATE_EMAIL)) fail('Please enter a valid email address');
  if(strlen($pass)<6) fail('Password must be at least 6 characters');
  $st=$pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1'); $st->execute([$email]); if($st->fetch()) fail('This email is already registered');
  $st=$pdo->prepare('SELECT id FROM users WHERE uid=? LIMIT 1'); $st->execute([$uid]); if($st->fetch()) fail('This Student / Staff ID is already registered');
  $st=$pdo->prepare('INSERT INTO users(name,email,password,uid,dept,role,status,is_admin) VALUES(?,?,?,?,?,?,"active",0)');
  $st->execute([$name,$email,password_hash($pass,PASSWORD_DEFAULT),$uid,$dept,$role]);
  $id=(int)$pdo->lastInsertId(); $st=$pdo->prepare('SELECT * FROM users WHERE id=?'); $st->execute([$id]); $u=$st->fetch();
  $_SESSION['user']=user_row($u); log_action('REGISTER','New user registered: '.$name,$id); notify_email($id,$email,'Welcome to CUCEK Lost and Found','Hello '.$name.', your CUCEK Lost and Found account has been created successfully.'); ok(['user'=>$_SESSION['user']]);
}
if($action==='logout'){ $u=$_SESSION['user']??null; if($u) log_action('LOGOUT',$u['name'].' logged out',!empty($u['isAdmin'])?null:$u['id']); session_destroy(); ok(); }
if($action==='dashboard'){
  $u=need_user();
  $st=$pdo->prepare('SELECT COUNT(*) FROM lost_items WHERE user_id=?'); $st->execute([$u['id']]); $lost=(int)$st->fetchColumn();
  $st=$pdo->prepare('SELECT COUNT(*) FROM found_items WHERE user_id=?'); $st->execute([$u['id']]); $found=(int)$st->fetchColumn();
  $st=$pdo->prepare('SELECT COUNT(*) FROM claims WHERE user_id=?'); $st->execute([$u['id']]); $claims=(int)$st->fetchColumn();
  $total=(int)$pdo->query('SELECT (SELECT COUNT(*) FROM lost_items)+(SELECT COUNT(*) FROM found_items)')->fetchColumn();
  $st=$pdo->prepare('SELECT c.*, f.location AS found_location, f.image_path AS item_image_path FROM claims c LEFT JOIN found_items f ON f.id=c.item_id WHERE c.user_id=? AND c.status="Approved" ORDER BY c.id DESC LIMIT 5');
  $st->execute([$u['id']]);
  $foundMessages=[];
  foreach($st->fetchAll() as $r){
    $foundMessages[]=[
      'itemName'=>$r['item_name'],
      'message'=>'Your item has been found. You can take it from the security room.',
      'location'=>$r['found_location']??'',
      'timestamp'=>$r['created_at'],
      'imagePath'=>$r['item_image_path']??''
    ];
  }
  $seenFoundMatches=[];
  foreach(user_match_notifications($u['id'],10) as $m){
    $seenFoundMatches[$m['lostItemId'].'-'.$m['itemId']]=1;
    $foundMessages[]=$m;
  }
  $st=$pdo->prepare('SELECT * FROM lost_items WHERE user_id=? AND status="Active" ORDER BY id DESC LIMIT 20');
  $st->execute([$u['id']]);
  foreach($st->fetchAll() as $lostRow){
    foreach(possible_found_matches($lostRow,$u['id'],3) as $match){
      $key=$lostRow['id'].'-'.$match['id'];
      if(isset($seenFoundMatches[$key])) continue;
      $seenFoundMatches[$key]=1;
      save_match_notification($u['id'],(int)$lostRow['id'],(int)$match['id'],(int)$match['matchScore'],$match['matchReason']);
      $foundMessages[]=[
        'itemName'=>$match['itemName'],
        'message'=>'Your item is available in claim section. You can claim it.',
        'location'=>$match['location']??'',
        'timestamp'=>$match['timestamp']??'',
        'imagePath'=>$match['imagePath']??'',
        'itemId'=>$match['id'],
        'matchScore'=>$match['matchScore'],
        'matchReason'=>$match['matchReason'],
        'lostItemName'=>$match['lostItemName']
      ];
    }
  }
  ok(['stats'=>['lost'=>$lost,'found'=>$found,'claims'=>$claims,'total'=>$total],'foundMessages'=>$foundMessages,'logs'=>array_map('log_row',$pdo->query('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 5')->fetchAll())]);
}
if($action==='browse'){
  $s='%'.f('search').'%'; $cat=f('category'); $status=f('status');
  $sql='SELECT * FROM found_items WHERE (?="" OR category=?) AND (?="" OR status=?) AND (item_name LIKE ? OR category LIKE ? OR location LIKE ? OR description LIKE ?) ORDER BY id DESC';
  $st=$pdo->prepare($sql); $st->execute([$cat,$cat,$status,$status,$s,$s,$s,$s]); ok(['items'=>array_map('item_row',$st->fetchAll())]);
}
if($action==='submit_lost'){
  $u=need_user(); $name=f('itemName'); $cat=f('category'); $date=f('dateLost'); $loc=f('location'); if(!$name||!$cat||!$date||!$loc) fail('Please fill all required fields');
  $st=$pdo->prepare('SELECT id FROM lost_items WHERE user_id=? AND status="Active" AND LOWER(TRIM(item_name))=LOWER(TRIM(?)) AND category=? LIMIT 1');
  $st->execute([$u['id'],$name,$cat]);
  if($st->fetch()) fail('Item is already registered.');
  $imagePath=save_item_image(f('imageData'),'lost');
  $pdo->prepare('INSERT INTO lost_items(user_id,user_name,item_name,category,description,keywords,image_path,date_lost,location,status) VALUES(?,?,?,?,?,?,?,?,?,"Active")')->execute([$u['id'],$u['name'],$name,$cat,f('description'),f('keywords'),$imagePath,$date,$loc]);
  $lost=['id'=>(int)$pdo->lastInsertId(),'user_id'=>$u['id'],'user_name'=>$u['name'],'item_name'=>$name,'category'=>$cat,'description'=>f('description'),'keywords'=>f('keywords'),'image_path'=>$imagePath,'date_lost'=>$date,'location'=>$loc,'status'=>'Active','created_at'=>date('Y-m-d H:i:s')];
  $matches=possible_found_matches($lost,$u['id'],5);
  foreach($matches as $match) save_match_notification($u['id'],$lost['id'],$match['id'],$match['matchScore'],$match['matchReason']);
  log_action('REPORT_LOST','Lost item reported: "'.$name.'" by '.$u['name'].($matches?' with '.count($matches).' possible match(es)':''),$u['id']); ok(['matches'=>$matches]);
}
if($action==='submit_found'){
  $u=need_user(); $name=f('itemName'); $cat=f('category'); $date=f('dateFound'); $loc=f('location'); if(!$name||!$cat||!$date||!$loc) fail('Please fill all required fields');
  $imagePath=save_item_image(f('imageData'),'found');
  $pdo->prepare('INSERT INTO found_items(user_id,user_name,item_name,category,description,keywords,image_path,date_found,location,status) VALUES(?,?,?,?,?,?,?,?,?,"Available")')->execute([$u['id'],$u['name'],$name,$cat,f('description'),f('keywords'),$imagePath,$date,$loc]);
  $found=['id'=>(int)$pdo->lastInsertId(),'user_id'=>$u['id'],'user_name'=>$u['name'],'item_name'=>$name,'category'=>$cat,'description'=>f('description'),'keywords'=>f('keywords'),'image_path'=>$imagePath,'date_found'=>$date,'location'=>$loc,'status'=>'Available','created_at'=>date('Y-m-d H:i:s')];
  $matches=[];
  foreach(possible_lost_matches($found,$u['id'],20) as $lostRow){
    save_match_notification($lostRow['userId'],$lostRow['id'],$found['id'],$lostRow['matchScore'],$lostRow['matchReason']);
    $matches[]=['lostItemId'=>$lostRow['id'],'lostItemName'=>$lostRow['itemName'],'userId'=>$lostRow['userId'],'matchScore'=>$lostRow['matchScore'],'matchReason'=>$lostRow['matchReason']];
  }
  log_action('REPORT_FOUND','Found item reported: "'.$name.'" by '.$u['name'].($matches?' matching '.count($matches).' lost report(s)':''),$u['id']); ok(['matches'=>$matches]);
}
if($action==='claim_info'){
  $u=need_user(); $id=(int)($in['itemId']??0); $st=$pdo->prepare('SELECT * FROM found_items WHERE id=?'); $st->execute([$id]); $it=$st->fetch(); if(!$it) fail('Item not found',404);
  if(empty($in['detailsOnly']) && $it['status']!=='Available') fail('This item is no longer available for claiming');
  if(empty($in['detailsOnly'])){ $st=$pdo->prepare('SELECT COUNT(*) FROM claims WHERE item_id=? AND user_id=? AND status="Pending"'); $st->execute([$id,$u['id']]); if($st->fetchColumn()>0) fail('You already have a pending claim for this item'); }
  ok(['item'=>item_row($it)]);
}
if($action==='submit_claim'){
  $u=need_user(); $id=(int)($in['itemId']??0); $details=f('details'); if(!$details) fail('Please provide claim details');
  if(!empty($u['isAdmin'])) fail('Admins cannot submit item claims. Please login as a user account.',403);
  $st=$pdo->prepare('SELECT * FROM found_items WHERE id=? AND status="Available"'); $st->execute([$id]); $it=$st->fetch(); if(!$it) fail('This item is no longer available for claiming');
  $linkedLostId=null;
  foreach(possible_lost_matches($it,null,20) as $lostMatch){
    if((int)$lostMatch['userId']===(int)$u['id']){ $linkedLostId=(int)$lostMatch['id']; break; }
  }
  $imagePath=save_item_image(f('imageData'),'claim');
  $pdo->prepare('INSERT INTO claims(item_id,lost_item_id,item_name,user_id,user_name,details,image_path,status) VALUES(?,?,?,?,?,?,?,"Pending")')->execute([$id,$linkedLostId,$it['item_name'],$u['id'],$u['name'],$details,$imagePath]);
  log_action('CLAIM_SUBMITTED','Claim submitted by '.$u['name'].' for "'.$it['item_name'].'"',$u['id']); notify_email($u['id'],$u['email'],'Claim submitted: '.$it['item_name'],'Your claim for '.$it['item_name'].' has been submitted and is waiting for admin review.'); ok();
}
if($action==='my_reports'){
  $u=need_user();
  $st=$pdo->prepare('SELECT * FROM lost_items WHERE user_id=? ORDER BY id DESC'); $st->execute([$u['id']]); $lost=array_map('item_row',$st->fetchAll());
  $st=$pdo->prepare('SELECT * FROM found_items WHERE user_id=? ORDER BY id DESC'); $st->execute([$u['id']]); $found=array_map('item_row',$st->fetchAll());
  $st=$pdo->prepare('SELECT c.*, c.image_path AS claim_image_path, u.name AS claim_user_name, f.image_path AS item_image_path, l.item_name AS lost_item_name FROM claims c LEFT JOIN users u ON u.id=c.user_id LEFT JOIN found_items f ON f.id=c.item_id LEFT JOIN lost_items l ON l.id=c.lost_item_id WHERE c.user_id=? ORDER BY c.id DESC'); $st->execute([$u['id']]); $claims=array_map('claim_row',$st->fetchAll()); ok(['lost'=>$lost,'found'=>$found,'claims'=>$claims,'matches'=>user_match_notifications($u['id'],20)]);
}
if($action==='delete_lost'){ $u=need_user(); $id=(int)($in['id']??0); $pdo->prepare('DELETE FROM lost_items WHERE id=? AND user_id=?')->execute([$id,$u['id']]); log_action('DELETE_REPORT','Lost item report deleted',$u['id']); ok(); }
if($action==='admin_dashboard'){
  need_admin(); $stats=['users'=>(int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_admin=0')->fetchColumn(),'lost'=>(int)$pdo->query('SELECT COUNT(*) FROM lost_items')->fetchColumn(),'found'=>(int)$pdo->query('SELECT COUNT(*) FROM found_items')->fetchColumn(),'claims'=>(int)$pdo->query('SELECT COUNT(*) FROM claims WHERE status="Pending"')->fetchColumn()];
  ok(['stats'=>$stats,'lost'=>array_map('item_row',$pdo->query('SELECT * FROM lost_items ORDER BY id DESC LIMIT 10')->fetchAll()),'found'=>array_map('item_row',$pdo->query('SELECT * FROM found_items ORDER BY id DESC LIMIT 10')->fetchAll())]);
}
if($action==='admin_delete_item'){ $u=need_admin(); $type=f('type'); $id=(int)($in['id']??0); $pdo->prepare($type==='lost'?'DELETE FROM lost_items WHERE id=?':'DELETE FROM found_items WHERE id=?')->execute([$id]); log_action('ADMIN_DELETE','Admin '.$u['name'].' removed a '.$type.' item'); ok(); }
if($action==='admin_claims'){ need_admin(); ok(['claims'=>array_map('claim_row',$pdo->query('SELECT c.*, c.image_path AS claim_image_path, u.name AS claim_user_name, f.image_path AS item_image_path, l.item_name AS lost_item_name FROM claims c LEFT JOIN users u ON u.id=c.user_id LEFT JOIN found_items f ON f.id=c.item_id LEFT JOIN lost_items l ON l.id=c.lost_item_id ORDER BY c.id DESC')->fetchAll())]); }
if($action==='approve_claim'){ $u=need_admin(); $id=(int)($in['id']??0); $st=$pdo->prepare('SELECT * FROM claims WHERE id=?'); $st->execute([$id]); $c=$st->fetch(); if(!$c) fail('Claim not found'); $pdo->prepare('UPDATE claims SET status="Approved" WHERE id=?')->execute([$id]); $pdo->prepare('UPDATE found_items SET status="Claimed" WHERE id=?')->execute([$c['item_id']]); if(!empty($c['lost_item_id'])){ $pdo->prepare('UPDATE lost_items SET status="Resolved" WHERE id=? AND user_id=? AND status="Active"')->execute([$c['lost_item_id'],$c['user_id']]); } else { $pdo->prepare('UPDATE lost_items SET status="Resolved" WHERE user_id=? AND LOWER(TRIM(item_name))=LOWER(TRIM(?)) AND status="Active"')->execute([$c['user_id'],$c['item_name']]); } log_action('CLAIM_APPROVED','Claim #'.$id.' for "'.$c['item_name'].'" approved by admin '.$u['name']); notify_user_id($c['user_id'],'Item found: '.$c['item_name'],'Your item has been found. You can take it from the security room.'); ok(); }
if($action==='reject_claim'){ $u=need_admin(); $id=(int)($in['id']??0); $st=$pdo->prepare('SELECT * FROM claims WHERE id=?'); $st->execute([$id]); $c=$st->fetch(); if(!$c) fail('Claim not found'); $pdo->prepare('UPDATE claims SET status="Rejected" WHERE id=?')->execute([$id]); log_action('CLAIM_REJECTED','Claim #'.$id.' for "'.$c['item_name'].'" rejected by admin '.$u['name']); notify_user_id($c['user_id'],'Claim rejected: '.$c['item_name'],'Your claim for '.$c['item_name'].' was reviewed and rejected by the admin.'); ok(); }
if($action==='admin_users'){ need_admin(); ok(['users'=>array_map('user_row',$pdo->query('SELECT * FROM users WHERE is_admin=0 ORDER BY id DESC')->fetchAll())]); }
if($action==='toggle_user_status'){ $u=need_admin(); $id=(int)($in['id']??0); $st=$pdo->prepare('SELECT * FROM users WHERE id=? AND is_admin=0'); $st->execute([$id]); $t=$st->fetch(); if(!$t) fail('User not found'); $new=$t['status']==='active'?'inactive':'active'; $pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute([$new,$id]); log_action('USER_STATUS','User '.$t['name'].' status changed to '.$new.' by admin '.$u['name']); ok(); }
if($action==='admin_logs'){ need_admin(); ok(['logs'=>array_map('log_row',$pdo->query('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 200')->fetchAll())]); }
fail('Unknown action',404);
} catch(PDOException $e) { if($e->getCode()==='23000') fail('Duplicate entry. This email may already be registered.'); fail('Database error: '.$e->getMessage(),500); }
?>
