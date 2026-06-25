import jwt from "jsonwebtoken";
import crypto from "crypto";
import "dotenv/config";

const accessSecret = process.env.JWT_ACCESS_SECRET;

export const generateAccessToken = (user) => {
  return jwt.sign(
    { id: user.id, email: user.email, role: user.role },
    process.env.JWT_ACCESS_SECRET,
    { expiresIn: process.env.JWT_ACCESS_EXPIRES_IN },
  );
};

export const generateClientToken = (client) => {
  const jti = crypto.randomUUID();

  return jwt.sign(
    {
      jti,
      client_id: client.client_id,
      role: "service",
    },
    process.env.JWT_ACCESS_SECRET,
    { expiresIn: process.env.JWT_ACCESS_EXPIRES_IN },
  );
};
