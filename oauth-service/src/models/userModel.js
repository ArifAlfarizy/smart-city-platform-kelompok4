import pool from "../configs/db.js";

export const createUser = async ({
  name,
  email,
  password_hash,
  photo,
  oauth_provider,
  oauth_id,
  role = "citizen",
}) => {
  const [result] = await pool.query(
    `INSERT INTO users (name, email, password_hash, photo, oauth_provider, oauth_id, role)
         VALUES (?, ?, ?, ?, ?, ?, ?)`,
    [name, email, password_hash, photo, oauth_provider, oauth_id, role],
  );

  const [rows] = await pool.query(
    `SELECT id, name, email, role FROM users WHERE id = ?`,
    [result.insertId],
  );

  return rows[0];
};

export const findUserByEmail = async (email) => {
  const [rows] = await pool.query("SELECT * FROM users WHERE email = ?", [email]);
  return rows[0];
};
