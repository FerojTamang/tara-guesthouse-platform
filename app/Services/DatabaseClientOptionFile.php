<?php
declare(strict_types=1);
namespace App\Services;
use InvalidArgumentException;
final class DatabaseClientOptionFile
{
 private ?string $path;
 /** @var \Closure(int):string */ private readonly \Closure $random;
 /** @param array{host:string,port:int,username:string,password:string,charset:string} $database */
 public function __construct(DatabaseBackupRoot $root,array $database,?callable $random=null){try{foreach(['host','username','password','charset']as$key)if(!isset($database[$key])||!is_string($database[$key])||preg_match('/[\x00\r\n]/',$database[$key])===1)throw new \RuntimeException();if(!isset($database['port'])||!is_int($database['port']))throw new \RuntimeException();$this->random=\Closure::fromCallable($random??static fn(int $n):string=>random_bytes($n));$bytes=($this->random)(12);if(!is_string($bytes)||strlen($bytes)!==12)throw new \RuntimeException();$this->path=$root->resolve('.db-client-'.bin2hex($bytes).'.cnf');$handle=@fopen($this->path,'x');if($handle===false)throw new \RuntimeException();$q=static fn(string $v):string=>'"'.str_replace(['\\','"'],['\\\\','\\"'],$v).'"';$data="[client]\nhost=".$q($database['host'])."\nport=".$database['port']."\nuser=".$q($database['username'])."\npassword=".$q($database['password'])."\ndefault-character-set=".$q($database['charset'])."\n";fwrite($handle,$data);fflush($handle);fclose($handle);@chmod($this->path,0600);}catch(\Throwable $e){if(isset($handle)&&is_resource($handle))fclose($handle);if(isset($this->path)&&is_string($this->path)&&is_file($this->path))@unlink($this->path);throw new InvalidArgumentException('Temporary database client configuration failed.');}}
 public function path():string{if($this->path===null)throw new InvalidArgumentException('Temporary database client configuration failed.');return $this->path;}
 public function cleanup():void{if($this->path!==null){@unlink($this->path);$this->path=null;}}
}
