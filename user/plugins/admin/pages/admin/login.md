---
title: 管理员登录
expires: 0
access:
  admin.login: false

forms:
  login:
    type: admin
    method: post

    fields:
      username:
        type: text
        placeholder: 用户名或邮箱
        autofocus: true
        validate:
          required: true

      password:
        type: password
        placeholder: 密码
        validate:
          required: true

  login-twofa:
    type: admin
    method: post

    fields:
      2fa_instructions:
        type: display
        markdown: true
        content: 双因素认证说明
      2fa_code:
        type: text
        id: twofa-code
        autofocus: true
        placeholder: 输入双因素认证代码
        description: 或
      yubikey_otp: 
        type
