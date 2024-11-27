---
title: erelcms注册管理员用户
expires: 0
access:
  admin.login: false

forms:
  admin-login-register:
    type: admin
    method: post

    fields:
      username:
        type: text
        label: PLUGIN_ADMIN.USERNAME
        autofocus: true
        placeholder: PLUGIN_ADMIN.USERNAME_PLACEHOLDER
        validate:
          required: true
          message: PLUGIN_LOGIN.USERNAME_NOT_VALID
          config-pattern@: system.username_regex

      email:
        type: email
        label: PLUGIN_ADMIN.EMAIL
        placeholder: "邮件地址"
        validate:
          type: email
          message: PLUGIN_ADMIN.EMAIL_VALIDATION_MESSAGE
          required: true

      password1:
        type: password
        label: PLUGIN_ADMIN.PASSWORD
        placeholder: PLUGIN_ADMIN.PWD_PLACEHOLDER
        validate:
          required: true
          message: PLUGIN_ADMIN.PASSWORD_VALIDATION_MESSAGE
          config-pattern@: system.pwd_regex

      password2:
        type: password
        label: PLUGIN_ADMIN.PASSWORD_CONFIRM
        placeholder: PLUGIN_ADMIN.PWD_PLACEHOLDER
        validate:
          required: true
          message: PLUGIN_ADMIN.PASSWORD_VALIDATION_MESSAGE
          config-pattern@: system.pwd_regex

      fullname:
        type: text
        placeholder: "后台显示名字'"
        label: PLUGIN_ADMIN.FULL_NAME
        validate:
          required: true

      title:
        type: text
        placeholder: "后台管理员名称"
        label: PLUGIN_ADMIN.TITLE
---

首次使用请创建**管理员**...
