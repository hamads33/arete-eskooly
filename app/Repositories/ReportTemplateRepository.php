<?php
declare(strict_types=1);
namespace App\Repositories;
use App\Core\Database;

final class ReportTemplateRepository
{
    public function __construct(private readonly Database $db){}
    public function find(int $institute,string $type):?array{$row=$this->db->query('SELECT * FROM report_templates WHERE institute_id=:i AND template_type=:t',['i'=>$institute,'t'=>$type])->fetch();return$row?:null;}
    public function all(int $institute):array{return$this->db->query('SELECT * FROM report_templates WHERE institute_id=:i ORDER BY template_type',['i'=>$institute])->fetchAll();}
    public function save(int $institute,string $type,array $data,int $user):void{$now=date('Y-m-d H:i:s');$this->db->query('INSERT INTO report_templates(institute_id,template_type,title,subtitle,body_html,footer_text,page_orientation,page_size,margin_mode,show_logo,show_address,show_phone,show_website,show_generated_date,show_page_numbers,primary_color,accent_color,settings_json,created_by,updated_by,created_at,updated_at) VALUES(:institute_id,:template_type,:title,:subtitle,:body_html,:footer_text,:page_orientation,:page_size,:margin_mode,:show_logo,:show_address,:show_phone,:show_website,:show_generated_date,:show_page_numbers,:primary_color,:accent_color,:settings_json,:created_by,:updated_by,:created_at,:updated_at) ON DUPLICATE KEY UPDATE title=VALUES(title),subtitle=VALUES(subtitle),body_html=VALUES(body_html),footer_text=VALUES(footer_text),page_orientation=VALUES(page_orientation),page_size=VALUES(page_size),margin_mode=VALUES(margin_mode),show_logo=VALUES(show_logo),show_address=VALUES(show_address),show_phone=VALUES(show_phone),show_website=VALUES(show_website),show_generated_date=VALUES(show_generated_date),show_page_numbers=VALUES(show_page_numbers),primary_color=VALUES(primary_color),accent_color=VALUES(accent_color),settings_json=VALUES(settings_json),updated_by=VALUES(updated_by),updated_at=VALUES(updated_at)',$data+['institute_id'=>$institute,'template_type'=>$type,'created_by'=>$user,'updated_by'=>$user,'created_at'=>$now,'updated_at'=>$now]);}
    public function reset(int $institute,string $type):void{$this->db->query('DELETE FROM report_templates WHERE institute_id=:i AND template_type=:t',['i'=>$institute,'t'=>$type]);}
}
