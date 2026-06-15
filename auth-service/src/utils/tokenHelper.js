import jwt from "jsonwebtoken";
import "dotenv/config";

const accessSecret = process.env.JWT_ACCESS_SECRET;

export const generateAccessToken = (user) => {
  return jwt.sign(
    { id: user.id, email: user.email, role: user.role },
    process.env.JWT_ACCESS_SECRET,
    { expiresIn: process.env.JWT_ACCESS_EXPIRES_IN },
  );
};