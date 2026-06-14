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
};

