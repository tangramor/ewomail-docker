#!/ewomail/php72/bin/php
<?php
// +----------------------------------------------------------------------
// | EwoMail
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://ewomail.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://ewomail.com/license.html)
// +----------------------------------------------------------------------
// | Author: Jun <gyxuehu@163.com>
// +----------------------------------------------------------------------

class init{
    
    public $db;

    public $host = 'ewomail_mysql';
    
    public $domain = 'ewomail.cn';
    
    //数据库名字
    public $mail_db = 'ewomail';
    //数据库账号
    public $mail_db_username = 'ewomail';
    
    public $root_pwd = 'ROOTPASSWORD';
    
    public $mail_pwd;
    
    public function __construct($domain){
        
        if(!$domain){
            die("Missing domain parameter");
        }

        $this->domain = $domain;
        $this->db = new mysqli($this->host,'root',$this->root_pwd,'mysql');
        if ($this->db->connect_error) {
            die('Connect Error('.$this->db->connect_errno.')'.$this->db->connect_error);
        }
        if (!$this->db->set_charset("utf8")) {
            die("Error loading character set utf8: ".$this->db->error);
        }
        
        $this->import_sql();
        $this->update_mail_config();
        $this->update_password();
        $this->update_file();
        $this->ending();
        
        echo "the configuration succeeds\n";
        
    }
    
    /**
     * 结束后更新文件
     * */
    public function ending()
    {
        $info = "domain:".$this->domain."\n";
        $info .= "mysql-root-password:".$this->root_pwd."\n";
        $info .= "mysql-ewomail-password:".$this->mail_pwd."\n";
        file_put_contents("/ewomail/config.ini",$info);
    }
    
    /**
     * 更新数据库密码
     * */
    public function update_password()
    {
        if(!$this->db->select_db('mysql')){
            die('Database switch to mysql failed');
        }
        //$root_pwd = $this->create_password();
        $mail_pwd = $this->create_password();
        //$this->root_pwd = $root_pwd;
        $this->mail_pwd = $mail_pwd;

        $sqlArr = [
            "DROP USER IF EXISTS '{$this->mail_db_username}'@'%'",
            "DROP USER IF EXISTS '{$this->mail_db_username}'@'localhost'",
            "DROP USER IF EXISTS '{$this->mail_db_username}'@'127.0.0.1'",
            "CREATE USER '{$this->mail_db_username}'@'%' IDENTIFIED WITH mysql_native_password BY '$mail_pwd'",
            "CREATE USER '{$this->mail_db_username}'@'localhost' IDENTIFIED WITH mysql_native_password BY '$mail_pwd'",
            "CREATE USER '{$this->mail_db_username}'@'127.0.0.1' IDENTIFIED WITH mysql_native_password BY '$mail_pwd'",
            "GRANT ALL PRIVILEGES ON `{$this->mail_db}`.* TO '{$this->mail_db_username}'@'%'",
            "GRANT ALL PRIVILEGES ON `{$this->mail_db}`.* TO '{$this->mail_db_username}'@'localhost'",
            "GRANT ALL PRIVILEGES ON `{$this->mail_db}`.* TO '{$this->mail_db_username}'@'127.0.0.1'",
            "ALTER USER '{$this->mail_db_username}'@'%'",
            "ALTER USER '{$this->mail_db_username}'@'localhost'",
            "ALTER USER '{$this->mail_db_username}'@'127.0.0.1'",
            "FLUSH PRIVILEGES"
        ];

        foreach($sqlArr as $v){
            if(!$this->db->query($v)){
                echo $v."\n";
                echo $this->db->error;
                exit;
            }
        }
        // $this->db->query("GRANT all privileges on *.* TO '{$this->mail_db_username}'@'localhost' IDENTIFIED BY '$mail_pwd'");
        // $this->db->query("GRANT all privileges on *.* TO '{$this->mail_db_username}'@'127.0.0.1' IDENTIFIED BY '$mail_pwd'");
        // $this->db->query("GRANT all privileges on *.* TO '{$this->mail_db_username}'@'%' IDENTIFIED BY '$mail_pwd'");
        
        // $this->db->query("UPDATE user SET password=PASSWORD('$root_pwd') WHERE user='root'");
        // $this->db->query("FLUSH PRIVILEGES");
        
        $this->update_password_file($mail_pwd);
        
    }
    
    /**
     * 修改相关数据库的文件配置
     * */
    public function update_password_file($password)
    {
        //修改dovecot数据库配置
        $dovecot_conf = [
            '/etc/dovecot/dovecot-sql.conf.ext',
            '/etc/dovecot/dovecot-dict-sql.conf.ext',
        ];
        
        foreach($dovecot_conf as $conf){
            $this->op_file($conf,function($line)use($password){
                if (trim($line) == '') {  
                    return $line;
                }
                $c = $line;
                if(preg_match('/^connect/',$line)){
                    $c = preg_replace("/password=.+/","password=".$password,$line);
                    $c = preg_replace("/host=[^ ]+ /","host=".$this->host." ",$c);
                }
                return $c;
            });
        }
        
        $postfix_conf = [
            '/etc/postfix/mysql/mysql_bcc_user.cf',
            '/etc/postfix/mysql/mysql-alias-maps.cf',
            '/etc/postfix/mysql/mysql-mailbox-domains.cf',
            '/etc/postfix/mysql/mysql-mailbox-maps.cf',
            '/etc/postfix/mysql/mysql-sender-login-maps.cf'
        ];
        
        foreach($postfix_conf as $conf){
            $this->op_file($conf,function($line)use($password){
                if (trim($line) == '') {  
                    return $line;
                }
                $c = $line;
                if(preg_match('/^password/',$line)){
                    $c = "password = ".$password."\n";
                }
                if(preg_match('/^hosts/',$c)){
                    $c = "hosts = ".$this->host."\n";
                }
                return $c;
            });
        }
        
        
        //修改ewomail配置文件
        $conf = '/ewomail/www/ewomail-admin/core/config.php';
        $this->op_file($conf,function($line)use($password){
            if (trim($line) == '') {  
                return $line;
            }
            $c = $line;
            if(preg_match("/'dbhost'/",$line)){
                $c = preg_replace("/'dbhost'.+/","'dbhost' => '".$this->host."',",$line);
            }
            if(preg_match("/'dbpw'/",$line)){
                $c = preg_replace("/'dbpw'.+/","'dbpw' => '".$password."',",$line);
            }
            if(preg_match("/'code_key'/",$c)){
                $c = preg_replace("/'code_key'.+/","'code_key' => '".$this->create_password()."',",$line);
            }
            if(preg_match("/'url'/",$c)){
                $url = "http://mail.".$this->domain.":8010";
                $c = preg_replace("/'url'.+/","'url' => '$url',",$line);
            }
            if(preg_match("/'webmail_url'/",$c)){
                $url = "http://mail.".$this->domain.":8000";
                $c = preg_replace("/'webmail_url'.+/","'webmail_url' => '$url',",$line);
            }
            return $c;
        });

    }
    
    /**
     * 修改配置文件
     * */
    public function update_file()
    {
        $amavisd_conf = '/etc/amavisd/amavisd.conf';
        $this->op_file($amavisd_conf,function($line){
            if (trim($line) == '') {  
                return $line;
            }
            $c = $line;
            if(preg_match('/^\\$mydomain/',$line)){
                $c = "\$mydomain = '{$this->domain}';\n";
            }
            if(preg_match('/\\$myhostname/',$line)){
                $c = "\$myhostname = 'mail.{$this->domain}';\n";
            }
            if(preg_match('/\\$final_virus_destiny/',$line)){
                $c = "#".$line;
            }
            if(preg_match('/\\$final_banned_destiny/',$line)){
                //$c = "\$final_banned_destiny = D_PASS;\n";
                $c = "#".$line;
            }
            if(preg_match('/\\$final_spam_destiny/',$line)){
                $c = "#".$line;
            }
            if(preg_match('/\\$final_bad_header_destiny/',$line)){
                //$c = "\$final_bad_header_destiny = D_PASS;\n";
                $c = "#".$line;
            }
            return $c;
        });
        
        $amavisd_str = file_get_contents($amavisd_conf);
        $amavisd_out = '$inet_socket_bind = "127.0.0.1";'."\n";
        $amavisd_out .= '$signed_header_fields{\'received\'} = 0;
$signed_header_fields{\'to\'} = 1;
$originating = 1;
                        
# Add dkim_key here.
dkim_key("'.$this->domain.'", "dkim", "/ewomail/dkim/mail.pem");
                        
@dkim_signature_options_bysender_maps = ({
# catchall defaults
\'.\' => {c => \'relaxed/simple\', ttl => 30*24*3600 },
} );';
        file_put_contents($amavisd_conf,$amavisd_str."\n".$amavisd_out);
        
        $postfix_conf = "/etc/postfix/main.cf";
        $this->op_file($postfix_conf,function($line){
            if (trim($line) == '') {  
                return $line;
            }
            $c = $line;
            if(preg_match('/^mydomain/',$line)){
                $c = "mydomain = {$this->domain}\n";
            }
            if(preg_match('/^myhostname/',$line)){
                $c = "myhostname = mail.{$this->domain}\n";
            }
            
            return $c;
        });
        
        //fail2ban
        $fail2ban_conf = "/etc/fail2ban/fail2ban.conf";
        $this->op_file($fail2ban_conf,function($line){
            if (trim($line) == '') {  
                return $line;
            }
            $c = $line;
            if(preg_match('/^logtarget/',$line)){
                $c = "logtarget = /var/log/fail2ban.log\n";
            }
            return $c;
        });
        
    }
    
    public function op_file($file,$fun)
    {
        $f = fopen($file,"r");
        $c = '';
        if($f){
            copy($file,$file.".backup");
            while (!feof($f)) {
                $line = fgets($f);
                $c .= $fun($line);
            }
            
            fclose($f);
            file_put_contents($file,$c);
        }
        
    }
    
    /**
     * 导入备份
     * */
    public function import_sql()
    {
        $sql_file = '/ewomail/www/ewomail-admin/upload/install.sql';
        $file = fopen($sql_file,"r");
        if(!$file){
            die("Data file read failed");
        }
        $sqlArr = [];
        $sql = '';
        $t = false;
        while (!feof($file)) {
            $line = fgets($file);
            if (trim($line) == '') {  
                continue;
            }
            
            if(preg_match('/^DROP TABLE IF EXISTS.+;/i',$line)){
                $sqlArr[] = $line;
            }
            
            if(preg_match('/^CREATE TABLE.+/i',$line)){
                $t = true;
            }
            if($t){
                $sql .= $line;
                if(preg_match('/ENGINE.+;/i',$line)){
                    $sqlArr[] = $sql;
                    $sql = '';
                    $t = false;
                }
            }
            
            if(preg_match('/^INSERT.+;/i',$line)){
                $sqlArr[] = $line;
            }
            
        }
        
        $r = $this->db->query("CREATE DATABASE IF NOT EXISTS ".$this->mail_db." DEFAULT CHARSET utf8 COLLATE utf8_general_ci");
        if(!$r){
            die('Database creation failed');
        }
        
        if(!$this->db->select_db($this->mail_db)){
            die('Database switch failed');
        }
        
        foreach($sqlArr as $v){
            if(!$this->db->query($v)){
                echo $v."\n";
                echo $this->db->error;
                exit;
            }
        }
        
        $sql = "INSERT INTO `i_admin_menu` VALUES ('104', '导入邮箱', '90104', '/Users/import', '100', '1', '0', '0', '0');";
        if(!$this->db->query($sql)){
            echo $sql."\n";
            echo $this->db->error;
            exit;
        }

        @unlink($sql_file);
    }
    
    /**
     * 修改数据里的mail配置
     * */
    public function update_mail_config()
    {
        //修改相关配置数据
        $imap = 'imap.'.$this->domain;
        $smtp = 'smtp.'.$this->domain;
        $mydomain = $this->domain;
        $myhostname = 'mail.'.$this->domain;
        $this->db->query("update i_mail_config set value='$imap' where name='imap'");
        $this->db->query("update i_mail_config set value='$smtp' where name='smtp'");
        $this->db->query("update i_mail_config set value='$mydomain' where name='mydomain'");
        $this->db->query("update i_mail_config set value='$myhostname' where name='myhostname'");
        $this->db->query("INSERT INTO i_domains (name,active,ctime) VALUES('$mydomain',1,NOW())");
    }
    
    /**
     * 创建密码
     * */
    function create_password( $length = 16 ) {
        // 密码字符集，可任意添加你需要的字符
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    
        $password = '';
        for ( $i = 0; $i < $length; $i++ ) 
        {
            // 这里提供两种字符获取方式
            // 第一种是使用 substr 截取$chars中的任意一位字符；
            // 第二种是取字符数组 $chars 的任意元素
            // $password .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
            $password .= $chars[ mt_rand(0, strlen($chars) - 1) ];
        }
    
        return $password;
    }
}

$init = new init($argv[1]);
$init->db->close();
?>