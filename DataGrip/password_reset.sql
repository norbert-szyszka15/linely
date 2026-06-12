UPDATE users
SET password_hash = '$argon2id$v=19$m=65536,t=4,p=1$ZEswdEtZUE03VE11NTVXLg$lvlpqdhTw8v2pQpwy4I8G0vQR2Lq1x02GJRvubnWJwY'
WHERE email = 'admin@example.com';

UPDATE users
SET password_hash = '$argon2id$v=19$m=65536,t=4,p=1$bTJmeGd6MXRXb1hKeHc4Zw$xUP/tyR34wkCGa+O1BPH7ozhLCDG9tuMg1LENeiPD2M'
WHERE email = 'user@example.com';