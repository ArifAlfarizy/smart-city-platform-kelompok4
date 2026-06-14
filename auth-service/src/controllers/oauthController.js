import { createUser, findUserByEmail } from "../models/userModel.js";
import "dotenv/config";
import bcrypt from "bcrypt";
import crypto from "crypto";
import { generateAccessToken } from "../utils/tokenHelper.js";
import { insertToken } from "../models/tokenModel.js";
const saltRounds = Number(process.env.SALT_ROUNDS);

export const token = async (req, res) => {
  const { grant_type } = req.body;

  const handler = grantHandlers[grant_type];

  if (!handler) {
    return res.status(400).json({
      success: false,
      message: "Unsupported grant type",
    });
  }

  return handler(req, res);
};

// using crypto to generate and hash refreshtokens
export const generateRefreshToken = () => {
  return crypto.randomBytes(64).toString("hex");
};

export const hashToken = (token) => {
  return crypto.createHash("sha256").update(token).digest("hex");
};

// Password grant
const passwordGrant = async (req, res) => {
  try {
    const { email, password } = req.body;

    if (!email || !password) {
      return res.status(400).json({
        success: false,
        message: "email and password must be filled",
      });
    }

    // Check existing user
    const checkExistingUser = await findUserByEmail(email);

    if (!checkExistingUser) {
      return res.status(400).json({
        // change code to duplicate
        success: false,
        message: "user not found. Try register!",
      });
    }

    const isPasswordMatch = await bcrypt.compare(
      password,
      checkExistingUser.password_hash,
    );

    if (!isPasswordMatch) {
      return res.status(401).json({
        success: false,
        message: "Invalid credentials",
      });
    }

    const accessToken = generateAccessToken(checkExistingUser);
    const refreshToken = generateRefreshToken();
    const hashedRefreshToken = hashToken(refreshToken);

    const expiredAt = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000);

    // save refresh token to database
    await insertToken({
      user_id: checkExistingUser.id,
      token_hash: hashedRefreshToken,
      expires_at: expiredAt,
    });

    res.status(200).json({
      success: true,
      accessToken,
      refreshToken,
      token_type: "Bearer",
      expiredAt,
    });
  } catch (error) {
    console.error("Granting password error:", error);
    return res.status(500).json({
      success: false,
      message: "Internal Server Error",
    });
  }
};

// Refresh token grant
const refreshTokenGrant = async (req, res) => {
  try {
  } catch (error) {}
};

// Client credentials grant

const grantHandlers = {
  password: passwordGrant,
  refresh_token: refreshTokenGrant,
};
