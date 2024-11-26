# Caddyfile 配置（适用于 Caddy 0.8.x 及以下版本）

:8080
gzip
fastcgi / 127.0.0.1:9000 php

# 开始 - 安全性
# 阻止直接访问以下文件夹
rewrite {
    r       /(\.git|cache|bin|logs|backups|tests)/.*$
    status  403
}

# 阻止在核心系统文件夹中运行脚本
rewrite {
    r       /(system|vendor)/.*\.(txt|xml|md|html|htm|shtml|shtm|yaml|yml|php|php2|php3|php4|php5|phar|phtml|pl|py|cgi|twig|sh|bat)$
    status  403
}

# 阻止在用户文件夹中运行脚本
rewrite {
    r       /user/.*\.(txt|md|yaml|yml|php|php2|php3|php4|php5|phar|phtml|pl|py|cgi|twig|sh|bat)$
    status  403
}

# 阻止访问根目录中的特定文件
rewrite {
    r       /(LICENSE\.txt|composer\.lock|composer\.json|nginx\.conf|web\.config|htaccess\.txt|\.htaccess)
    status  403
}
## 结束 - 安全性

# 全局重写规则应放在最后
rewrite {
    to  {path} {path}/ /index.php?_url={uri}&{query}
}
