<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>运维工单上报</title>
    <script src="../js/jquery-3.6.4.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Microsoft YaHei", sans-serif;
        }
        body {
            background-color: #f5f7fa;
            color: #1f2d3d;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
        }
        h2 {
            text-align: center;
            margin-bottom: 24px;
            color: #303133;
        }
        .system-info {
            background-color: #f0f9ff;
            border: 1px solid #b3d8ff;
            border-radius: 4px;
            padding: 16px;
            margin-bottom: 24px;
        }
        .system-info h3 {
            font-size: 16px;
            color: #409eff;
            margin-bottom: 12px;
        }
        .system-info p {
            font-size: 14px;
            color: #606266;
            margin-bottom: 8px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: #606266;
        }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #dcdfe6;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-control:focus {
            outline: none;
            border-color: #409eff;
        }
        .form-control.error {
            border-color: #f56c6c;
        }
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        .code-group {
            display: flex;
            gap: 10px;
        }
        .code-group .form-control {
            flex: 1;
        }
        .btn-code {
            padding: 10px 16px;
            background-color: #409eff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            white-space: nowrap;
        }
        .btn-code:disabled {
            background-color: #c0c4cc;
            cursor: not-allowed;
        }
        .btn-submit {
            width: 100%;
            padding: 12px;
            background-color: #409eff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }
        .btn-submit:hover {
            background-color: #66b1ff;
        }
        .message {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }
        .success-message {
            color: #67c23a;
        }
        .error-message {
            color: #f56c6c;
        }
        .tip {
            font-size: 12px;
            color: #909399;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>运维工单上报</h2>
        
        <div class="system-info" id="systemInfo">
            <h3 id="systemName">加载中...</h3>
            <p><strong>客户名称：</strong><span id="customerName">--</span></p>
            <p><strong>项目经理：</strong><span id="pmName">--</span> (<span id="pmPhone">--</span>)</p>
            <p><strong>专属工程师：</strong><span id="engineerName">--</span></p>
        </div>
        
        <form id="ticketForm">
            <input type="hidden" id="systemId" value="">
            
            <div class="form-group">
                <label for="contactName">联系人姓名 <span style="color: #f56c6c;">*</span></label>
                <input type="text" id="contactName" name="contactName" class="form-control" placeholder="请输入联系人姓名" required>
            </div>
            
            <div class="form-group">
                <label for="contactPhone">联系电话 <span style="color: #f56c6c;">*</span></label>
                <div class="code-group">
                    <input type="tel" id="contactPhone" name="contactPhone" class="form-control" placeholder="请输入手机号码" required>
                    <button type="button" id="sendCodeBtn" class="btn-code">发送验证码</button>
                </div>
            </div>
            
            <div class="form-group">
                <label for="verifyCode">验证码 <span style="color: #f56c6c;">*</span></label>
                <input type="text" id="verifyCode" name="verifyCode" class="form-control" placeholder="请输入验证码" required>
            </div>
            
            <div class="form-group">
                <label for="title">问题标题 <span style="color: #f56c6c;">*</span></label>
                <input type="text" id="title" name="title" class="form-control" placeholder="请输入问题标题" required>
            </div>
            
            <div class="form-group">
                <label for="problemType">问题类型 <span style="color: #f56c6c;">*</span></label>
                <select id="problemType" name="problemType" class="form-control" required>
                    <option value="">请选择问题类型</option>
                    <option value="1">系统故障</option>
                    <option value="2">网络问题</option>
                    <option value="3">软件安装</option>
                    <option value="4">硬件维护</option>
                    <option value="5">账号权限</option>
                    <option value="6">其他请求</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="priority">优先级 <span style="color: #f56c6c;">*</span></label>
                <select id="priority" name="priority" class="form-control" required>
                    <option value="">请选择优先级</option>
                    <option value="1">低 - 不影响正常工作，可延后处理</option>
                    <option value="2">中 - 轻微影响工作，需按计划处理</option>
                    <option value="3">高 - 严重影响工作，需立即处理</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="description">问题描述 <span style="color: #f56c6c;">*</span></label>
                <textarea id="description" name="description" class="form-control" placeholder="请详细描述问题现象、影响范围等信息" required></textarea>
            </div>
            
            <div class="form-group">
                <label>附件上传</label>
                <div class="upload-section">
                    <button type="button" class="btn-code" onclick="$('#fileInput').click()">选择文件</button>
                    <input type="file" id="fileInput" multiple style="display: none;">
                    <div id="uploadFilesList" style="margin-top: 10px;"></div>
                </div>
                <div class="tip">支持上传图片、文档等文件，单个文件不超过5MB</div>
            </div>
            
            <button type="submit" class="btn-submit">提交工单</button>
            
            <div id="submitMessage" class="message"></div>
        </form>
    </div>

    <script>
        $(function() {
            const urlParams = new URLSearchParams(window.location.search);
            const systemId = urlParams.get('system_id');
            
            if (!systemId) {
                $('#systemInfo').html('<h3 style="color: #f56c6c;">错误：未指定运维系统</h3>');
                $('#ticketForm').hide();
                return;
            }
            
            $('#systemId').val(systemId);
            
            function loadSystemInfo() {
                $.ajax({
                    url: 'list.php',
                    type: 'POST',
                    dataType: 'json',
                    contentType: 'application/json',
                    data: JSON.stringify({ system_id: systemId }),
                    success: function(res) {
                        if (res.success && res.data.list.length > 0) {
                            const system = res.data.list[0];
                            
                            if (system.status !== 1) {
                                $('#systemInfo').html('<h3 style="color: #f56c6c;">错误：该系统已停用</h3>');
                                $('#ticketForm').hide();
                                return;
                            }
                            
                            $('#systemName').text(system.system_name);
                            $('#customerName').text(system.customer_name || '--');
                            $('#pmName').text(system.pm_name || '--');
                            $('#pmPhone').text(system.pm_phone || '--');
                            $('#engineerName').text(system.engineer_name || '--');
                        } else {
                            $('#systemInfo').html('<h3 style="color: #f56c6c;">错误：系统信息不存在</h3>');
                            $('#ticketForm').hide();
                        }
                    },
                    error: function() {
                        $('#systemInfo').html('<h3 style="color: #f56c6c;">错误：加载系统信息失败</h3>');
                    }
                });
            }
            
            let countdown = 0;
            let timer = null;
            
            function updateCodeButton() {
                if (countdown > 0) {
                    $('#sendCodeBtn').prop('disabled', true);
                    $('#sendCodeBtn').text(`${countdown}秒后重发`);
                    countdown--;
                } else {
                    $('#sendCodeBtn').prop('disabled', false);
                    $('#sendCodeBtn').text('发送验证码');
                    if (timer) {
                        clearInterval(timer);
                        timer = null;
                    }
                }
            }
            
            $('#sendCodeBtn').click(function() {
                const phone = $('#contactPhone').val().trim();
                
                if (!phone) {
                    $('#contactPhone').addClass('error');
                    return;
                }
                
                const phoneRegex = /^1[3-9]\d{9}$/;
                if (!phoneRegex.test(phone)) {
                    alert('请输入正确的手机号码');
                    $('#contactPhone').addClass('error');
                    return;
                }
                
                $('#contactPhone').removeClass('error');
                
                const code = Math.floor(100000 + Math.random() * 900000).toString();
                console.log('验证码：' + code);
                
                alert('验证码已发送至您的手机，验证码为：' + code + '（演示环境）');
                
                countdown = 60;
                updateCodeButton();
                timer = setInterval(updateCodeButton, 1000);
            });
            
            $('#ticketForm').submit(function(e) {
                e.preventDefault();
                
                const contactName = $('#contactName').val().trim();
                const contactPhone = $('#contactPhone').val().trim();
                const verifyCode = $('#verifyCode').val().trim();
                const title = $('#title').val().trim();
                const problemType = $('#problemType').val();
                const priority = $('#priority').val();
                const description = $('#description').val().trim();
                
                if (!contactName || !contactPhone || !verifyCode || !title || !problemType || !priority || !description) {
                    alert('请填写所有必填字段');
                    return;
                }
                
                const phoneRegex = /^1[3-9]\d{9}$/;
                if (!phoneRegex.test(contactPhone)) {
                    alert('请输入正确的手机号码');
                    return;
                }
                
                if (!/^\d{6}$/.test(verifyCode)) {
                    alert('请输入6位数字验证码');
                    return;
                }
                
                const formData = new FormData();
                formData.append('system_id', parseInt(systemId));
                formData.append('contact_name', contactName);
                formData.append('contact_phone', contactPhone);
                formData.append('verify_code', verifyCode);
                formData.append('title', title);
                formData.append('problem_type', problemType);
                formData.append('priority', priority);
                formData.append('description', description);
                
                uploadedFiles.forEach(file => {
                    formData.append('attachments[]', file);
                });
                
                $.ajax({
                    url: '../submit_ticket.php',
                    type: 'POST',
                    dataType: 'json',
                    contentType: false,
                    processData: false,
                    data: formData,
                    success: function(res) {
                        if (res.success) {
                            $('#submitMessage').html('<span class="success-message">工单提交成功！工单编号：' + res.data.ticket_id + '</span>');
                            $('#ticketForm')[0].reset();
                            $('#systemId').val(systemId);
                            uploadedFiles.length = 0;
                            updateFileList();
                        } else {
                            $('#submitMessage').html('<span class="error-message">' + (res.message || '工单提交失败') + '</span>');
                        }
                    },
                    error: function() {
                        $('#submitMessage').html('<span class="error-message">网络错误，请稍后重试</span>');
                    }
                });
            });
            
            $('.form-control').focus(function() {
                $(this).removeClass('error');
            });
            
            let uploadedFiles = [];
            
            $('#fileInput').change(function(e) {
                const files = e.target.files;
                if (files.length > 0) {
                    for (let i = 0; i < files.length; i++) {
                        const file = files[i];
                        if (file.size > 5 * 1024 * 1024) {
                            alert('文件大小不能超过5MB');
                            continue;
                        }
                        uploadedFiles.push(file);
                    }
                    updateFileList();
                    $(this).val('');
                }
            });
            
            function updateFileList() {
                const $fileList = $('#uploadFilesList');
                $fileList.html('');
                
                uploadedFiles.forEach((file, index) => {
                    $fileList.append(`
                        <div style="display: flex; align-items: center; padding: 5px 10px; background-color: #f5f5f5; border-radius: 4px; margin-bottom: 5px;">
                            <span style="flex: 1;">${file.name}</span>
                            <span style="font-size: 12px; color: #999; margin-right: 10px;">${(file.size / 1024).toFixed(2)}KB</span>
                            <span style="color: #f56c6c; cursor: pointer;" onclick="removeFile(${index})">删除</span>
                        </div>
                    `);
                });
            }
            
            window.removeFile = function(index) {
                uploadedFiles.splice(index, 1);
                updateFileList();
            };
            
            loadSystemInfo();
        });
    </script>
</body>
</html>
