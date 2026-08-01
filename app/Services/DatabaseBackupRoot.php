<?php
declare(strict_types=1);
namespace App\Services;
use InvalidArgumentException;

final readonly class DatabaseBackupRoot
{
 private string $root;
 public function __construct(string $storageRoot, string $publicRoot)
 {
  if (preg_match('/[\x00\r\n]/',$storageRoot.$publicRoot)===1 || !is_dir($storageRoot)) throw new InvalidArgumentException('Backup path is unsafe.');
  $storage=realpath($storageRoot); $public=realpath($publicRoot); if($storage===false||$public===false) throw new InvalidArgumentException('Backup path is unsafe.');
  $root=$storage.DIRECTORY_SEPARATOR.'backups'; if(!is_dir($root)&&!@mkdir($root,0700)) throw new InvalidArgumentException('Backup path is unsafe.'); $resolved=realpath($root);
  $norm=static fn(string $p):string=>DIRECTORY_SEPARATOR==='\\'?strtolower($p):$p;
  if($resolved===false||!str_starts_with($norm($resolved),rtrim($norm($storage),DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)||str_starts_with($norm($resolved),rtrim($norm($public),DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) throw new InvalidArgumentException('Backup path is unsafe.');
  $this->root=$resolved;
 }
 public function resolve(string $basename):string { if($basename===''||$basename==='.'||$basename==='..'||preg_match('/^[^\\\\\/\:\x00\r\n]+$/D',$basename)!==1||$basename!==basename($basename)) throw new InvalidArgumentException('Backup path is unsafe.'); return $this->root.DIRECTORY_SEPARATOR.$basename; }
}
