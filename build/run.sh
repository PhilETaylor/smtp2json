docker run -d --always -p25:25 philetaylor/smtp2json:latest
  docker run -p25:25 -v `pwd`/.env:/app/.env philetaylor/smtp2json:latest