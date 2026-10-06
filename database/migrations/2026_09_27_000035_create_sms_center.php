<?php
declare(strict_types=1);
/**
 * SMS Center: an institute-scoped queue that a paired Android device (the SMS bridge) drains
 * through /api/sms-gateway/v1. Settings stay in the existing sms_settings row (provider
 * 'android_gateway', options in configuration_json), so no second settings store is added.
 */
return static function(PDO $pdo):void{
    $opts='ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $pdo->exec("CREATE TABLE IF NOT EXISTS sms_templates(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,institute_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(120) NOT NULL,slug VARCHAR(140) NOT NULL,category VARCHAR(30) NOT NULL DEFAULT 'general',
        message_body TEXT NOT NULL,variables_json TEXT NULL,is_active TINYINT(1) NOT NULL DEFAULT 1,is_system TINYINT(1) NOT NULL DEFAULT 0,
        created_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,
        UNIQUE KEY uq_sms_template_slug(institute_id,slug),
        CONSTRAINT fk_sms_template_institute FOREIGN KEY(institute_id) REFERENCES institutes(id) ON DELETE CASCADE,
        CONSTRAINT fk_sms_template_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL) $opts");
    $pdo->exec("CREATE TABLE IF NOT EXISTS sms_gateway_devices(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,institute_id BIGINT UNSIGNED NOT NULL,
        device_uuid VARCHAR(100) NULL,device_name VARCHAR(120) NOT NULL,phone_number VARCHAR(20) NULL,
        sim_name VARCHAR(80) NULL,sim_slot TINYINT UNSIGNED NULL,manufacturer VARCHAR(80) NULL,model VARCHAR(80) NULL,
        android_version VARCHAR(20) NULL,app_version VARCHAR(20) NULL,battery_level TINYINT UNSIGNED NULL,network VARCHAR(20) NULL,
        status VARCHAR(12) NOT NULL DEFAULT 'pending',last_seen_at DATETIME NULL,last_ip VARCHAR(45) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,created_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,
        UNIQUE KEY uq_sms_device_uuid(device_uuid),INDEX idx_sms_device_institute(institute_id,is_active),
        CONSTRAINT fk_sms_device_institute FOREIGN KEY(institute_id) REFERENCES institutes(id) ON DELETE CASCADE,
        CONSTRAINT fk_sms_device_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL) $opts");
    $pdo->exec("CREATE TABLE IF NOT EXISTS sms_gateway_tokens(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,gateway_device_id BIGINT UNSIGNED NOT NULL,
        token_type VARCHAR(10) NOT NULL DEFAULT 'access',token_hash CHAR(64) NOT NULL,token_hint VARCHAR(12) NOT NULL,
        expires_at DATETIME NULL,last_used_at DATETIME NULL,revoked_at DATETIME NULL,created_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL,
        UNIQUE KEY uq_sms_token_hash(token_hash),INDEX idx_sms_token_device(gateway_device_id,token_type),
        CONSTRAINT fk_sms_token_device FOREIGN KEY(gateway_device_id) REFERENCES sms_gateway_devices(id) ON DELETE CASCADE,
        CONSTRAINT fk_sms_token_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL) $opts");
    $pdo->exec("CREATE TABLE IF NOT EXISTS sms_messages(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,institute_id BIGINT UNSIGNED NOT NULL,
        recipient_type VARCHAR(12) NOT NULL DEFAULT 'manual',recipient_id BIGINT UNSIGNED NULL,recipient_name VARCHAR(160) NULL,
        phone_number VARCHAR(20) NOT NULL,message TEXT NOT NULL,message_type VARCHAR(20) NOT NULL DEFAULT 'manual',
        priority VARCHAR(8) NOT NULL DEFAULT 'normal',priority_rank TINYINT UNSIGNED NOT NULL DEFAULT 2,
        status VARCHAR(12) NOT NULL DEFAULT 'queued',source VARCHAR(24) NOT NULL DEFAULT 'manual',
        template_id BIGINT UNSIGNED NULL,batch_id VARCHAR(32) NULL,dedupe_key VARCHAR(160) NULL,
        segments TINYINT UNSIGNED NOT NULL DEFAULT 1,is_unicode TINYINT(1) NOT NULL DEFAULT 0,
        scheduled_at DATETIME NULL,available_at DATETIME NULL,reserved_at DATETIME NULL,processing_at DATETIME NULL,
        sent_at DATETIME NULL,failed_at DATETIME NULL,cancelled_at DATETIME NULL,failure_code VARCHAR(40) NULL,failure_reason VARCHAR(255) NULL,
        retry_count TINYINT UNSIGNED NOT NULL DEFAULT 0,max_retries TINYINT UNSIGNED NOT NULL DEFAULT 3,
        gateway_device_id BIGINT UNSIGNED NULL,sim_slot TINYINT UNSIGNED NULL,external_reference VARCHAR(120) NULL,
        created_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,
        UNIQUE KEY uq_sms_dedupe(institute_id,dedupe_key),
        INDEX idx_sms_dispatch(institute_id,status,priority_rank,id),
        INDEX idx_sms_created(institute_id,created_at),INDEX idx_sms_device(gateway_device_id,status),INDEX idx_sms_batch(batch_id),
        CONSTRAINT fk_sms_msg_institute FOREIGN KEY(institute_id) REFERENCES institutes(id) ON DELETE CASCADE,
        CONSTRAINT fk_sms_msg_template FOREIGN KEY(template_id) REFERENCES sms_templates(id) ON DELETE SET NULL,
        CONSTRAINT fk_sms_msg_device FOREIGN KEY(gateway_device_id) REFERENCES sms_gateway_devices(id) ON DELETE SET NULL,
        CONSTRAINT fk_sms_msg_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL) $opts");
    $pdo->exec("CREATE TABLE IF NOT EXISTS sms_automation_rules(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,institute_id BIGINT UNSIGNED NOT NULL,event_type VARCHAR(40) NOT NULL,
        template_id BIGINT UNSIGNED NULL,enabled TINYINT(1) NOT NULL DEFAULT 0,recipient_target VARCHAR(20) NOT NULL DEFAULT 'guardian',
        conditions_json TEXT NULL,created_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,
        UNIQUE KEY uq_sms_rule_event(institute_id,event_type),
        CONSTRAINT fk_sms_rule_institute FOREIGN KEY(institute_id) REFERENCES institutes(id) ON DELETE CASCADE,
        CONSTRAINT fk_sms_rule_template FOREIGN KEY(template_id) REFERENCES sms_templates(id) ON DELETE SET NULL,
        CONSTRAINT fk_sms_rule_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL) $opts");
    $pdo->exec("CREATE TABLE IF NOT EXISTS sms_logs(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,institute_id BIGINT UNSIGNED NOT NULL,
        sms_message_id BIGINT UNSIGNED NULL,gateway_device_id BIGINT UNSIGNED NULL,event VARCHAR(40) NOT NULL,
        actor_type VARCHAR(10) NOT NULL DEFAULT 'system',actor_id BIGINT UNSIGNED NULL,actor_name VARCHAR(160) NULL,
        details_json TEXT NULL,created_at DATETIME NOT NULL,
        INDEX idx_sms_log_institute(institute_id,created_at),INDEX idx_sms_log_message(sms_message_id),INDEX idx_sms_log_device(gateway_device_id),
        CONSTRAINT fk_sms_log_institute FOREIGN KEY(institute_id) REFERENCES institutes(id) ON DELETE CASCADE) $opts");
    $pdo->exec("CREATE TABLE IF NOT EXISTS sms_api_rate_limits(
        bucket VARCHAR(80) NOT NULL,window_start DATETIME NOT NULL,hits INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY(bucket,window_start)) $opts");

    $now=date('Y-m-d H:i:s');
    $stmt=$pdo->prepare('INSERT IGNORE INTO permissions(name,slug,created_at,updated_at) VALUES(:n,:s,:c,:u)');
    foreach([
        'sms.view'=>'View SMS Center','sms.compose'=>'Compose individual SMS','sms.bulk_send'=>'Queue bulk SMS',
        'sms.templates.manage'=>'Manage SMS templates','sms.queue.manage'=>'Manage the SMS queue',
        'sms.settings.manage'=>'Manage SMS settings and automations','sms.gateway.manage'=>'Manage SMS gateway devices',
        'sms.logs.view'=>'View SMS logs',
    ] as $slug=>$name)$stmt->execute(['n'=>$name,'s'=>$slug,'c'=>$now,'u'=>$now]);
};
