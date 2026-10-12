<?php
declare(strict_types=1);
namespace LeafPHP\Core;
use LeafPHP\Database\DB;
use SessionHandlerInterface;
final class DatabaseSessionHandler implements SessionHandlerInterface
{
 public function open(string $path,string $name): bool{return true;} public function close(): bool{return true;}
 public function read(string $id): string|false { $row=DB::table('sessions')->where('id',$id)->first();if(!$row)return '';if((int)$row['last_activity']<time()-$this->lifetime()){$this->destroy($id);return '';}return base64_decode((string)$row['payload'],true)?:''; }
 public function write(string $id,string $data): bool { $values=['id'=>$id,'user_id'=>$_SESSION['_leafphp_auth_user_id']??null,'ip_address'=>substr((string)($_SERVER['REMOTE_ADDR']??''),0,45),'user_agent'=>substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500),'payload'=>base64_encode($data),'last_activity'=>time()];DB::table('sessions')->upsert($values,['id'],['user_id','ip_address','user_agent','payload','last_activity']);return true; }
 public function destroy(string $id): bool {DB::table('sessions')->where('id',$id)->delete();return true;} public function gc(int $max_lifetime): int|false {return DB::table('sessions')->where('last_activity','<',time()-$max_lifetime)->delete();}
 private function lifetime(): int{return (int)config('app.session_lifetime',120)*60;}
}
