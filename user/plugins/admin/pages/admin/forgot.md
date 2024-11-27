---
title: 忘记密码
expires: 0
access:
  admin.login: false

forms:
  admin-login-forgot:
    type: admin
    method: post

    fields:
      username:
        type: text
        placeholder: 用户名
        autofocus: true
        validate:
          required: true
---
