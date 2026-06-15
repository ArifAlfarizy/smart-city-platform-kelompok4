import pool from "../configs/db.js";

export const insertToken = async ({
  user_id,
  token_hash,
  is_revoked = 0,
  expires_at,
}) => {
  const [result] = await pool.query(
    `INSERT INTO refresh_tokens (user_id, token_hash, is_revoked, expires_at)
        VALUES (?, ?, ?, ?)`,
    [user_id, token_hash, is_revoked, expires_at],
  );

  const [rows] = await pool.query(
    `SELECT user_id, token_hash, is_revoked, expires_at FROM refresh_tokens WHERE id = ?`,
    [result.insertId],
  );

  return rows[0];
};

export const findRefreshToken = async (token_hash) => {
  const [rows] = await pool.query(
    `SELECT * FROM refresh_tokens
     WHERE token_hash = ? AND is_revoked = 0 AND expires_at > NOW()
     LIMIT 1`,
    [token_hash]
  );
  return rows[0];
};

export const revokeRefreshToken = async (token_hash) => {
  await pool.query(
    `UPDATE refresh_tokens SET is_revoked = 1 WHERE token_hash = ?`,
    [token_hash]
  );
};
 
export const revokeAllUserTokens = async (user_id) => {
  await pool.query(
    `UPDATE refresh_tokens SET is_revoked = 1 WHERE user_id = ?`,
    [user_id]
  );
};

export const insertRevokedToken = async ({ jti, expires_at }) => {
  await pool.query(
    `INSERT IGNORE INTO revoked_tokens (jti, expires_at) VALUES (?, ?)`,
    [jti, expires_at]
  );
};
 
export const isTokenRevoked = async (jti) => {
  const [rows] = await pool.query(
    `SELECT id FROM revoked_tokens WHERE jti = ? LIMIT 1`,
    [jti]
  );
  return rows.length > 0;
};

export const findClientById = async (client_id) => {
  const [rows] = await pool.query(
    `SELECT * FROM oauth_clients WHERE client_id = ? LIMIT 1`,
    [client_id]
  );
  return rows[0];
};