---
title: 重置密码
expires: 0
access:
  admin.login: false


forms:
  admin-login-reset:
    type: admin
    method: post

    fields:
      username:
        type: text
        placeholder: 用户名
        readonly: true
      password:
        type: password
        placeholder: 密码
        autofocus: true
      token:
        type: hidden
---
