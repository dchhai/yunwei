-- 创建附件二进制数据存储表
CREATE TABLE IF NOT EXISTS ticket_attachment_blob (
    id INT(11) NOT NULL AUTO_INCREMENT COMMENT '附件ID（主键）',
    ticket_id INT(11) DEFAULT NULL COMMENT '关联工单ID',
    reply_id INT(11) DEFAULT NULL COMMENT '关联回复ID',
    file_name VARCHAR(255) NOT NULL COMMENT '原始文件名',
    file_type VARCHAR(100) DEFAULT NULL COMMENT '文件类型',
    file_size INT(11) NOT NULL COMMENT '文件大小（字节）',
    file_data LONGBLOB NOT NULL COMMENT '文件二进制数据',
    create_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (id),
    KEY idx_ticket_id (ticket_id),
    KEY idx_reply_id (reply_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工单附件二进制数据表';
