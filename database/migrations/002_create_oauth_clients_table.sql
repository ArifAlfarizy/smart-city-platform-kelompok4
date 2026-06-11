CREATE TABLE oauth_clients(
  id            INT AUTO_INCREMENT PRIMARY KEY,
  client_id     VARCHAR(100) NOT NULL UNIQUE,
  client_secret VARCHAR(255) NOT NULL,
  grant_types   VARCHAR(255),  -- "password,client_credentials,refresh_token"
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);