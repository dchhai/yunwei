-- 为ticket表添加联系人字段
-- 执行此SQL语句来添加contact_name和contact_phone字段

ALTER TABLE ticket 
ADD COLUMN IF NOT EXISTS contact_name VARCHAR(50) DEFAULT NULL COMMENT '联系人姓名',
ADD COLUMN IF NOT EXISTS contact_phone VARCHAR(20) DEFAULT NULL COMMENT '联系电话';

-- 如果上面的语句不支持，请使用以下分开的语句：
-- ALTER TABLE ticket ADD COLUMN contact_name VARCHAR(50) DEFAULT NULL COMMENT '联系人姓名';
-- ALTER TABLE ticket ADD COLUMN contact_phone VARCHAR(20) DEFAULT NULL COMMENT '联系电话';
