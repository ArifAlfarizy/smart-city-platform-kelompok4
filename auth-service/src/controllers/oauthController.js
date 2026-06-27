import { findUserByEmail, findUserById } from "../models/userModel.js";
import {
  insertToken,
  findRefreshToken,
  revokeRefreshToken,
  revokeAllUserTokens,
  insertRevokedToken,
  findClientById,
  isTokenRevoked,
} from "../models/tokenModel.js";

import {
  generateAccessToken,
  generateClientToken,
} from "../utils/tokenHelper.js";
import bcrypt from "bcrypt";
import crypto from "crypto";
import jwt from "jsonwebtoken";
import "dotenv/config";

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

const REFRESH_TOKEN_TTL_MS = 7 * 24 * 60 * 60 * 1000;

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
      return res.status(404).json({
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
      access_token: accessToken,
      refresh_token: refreshToken,
      token_type: "Bearer",
      expires_in: Number(process.env.JWT_ACCESS_EXPIRES_IN),
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
    const { refresh_token } = req.body;

    if (!refresh_token) {
      return res.status(400).json({
        success: false,
        message: "refresh_token must be filled",
      });
    }

    const hashed = hashToken(refresh_token);
    const record = await findRefreshToken(hashed);

    if (!record) {
      return res.status(401).json({
        success: false,
        message: "Invalid refresh token, please relogin",
      });
    }

    const user = await findUserById(record.user_id);

    if (!user) {
      return res.status(401).json({
        success: false,
        message: "User not found",
      });
    }

    // delete old and create new
    await revokeRefreshToken(hashed);
    const { accessToken, refreshToken: newRefreshToken } =
      await issueTokenPair(user);

    return res.status(200).json({
      success: true,
      access_token: accessToken,
      refresh_token: newRefreshToken,
      token_type: "Bearer",
      expires_in: Number(process.env.JWT_ACCESS_EXPIRES_IN),
    });
  } catch (error) {
    console.error("Refresh token grant error:", error);
    return res
      .status(500)
      .json({ success: false, message: "Internal Server Error" });
  }
};

// client credentials
const clientCredentialsGrant = async (req, res) => {
  try {
    const { client_id, client_secret } = req.body;

    if (!client_id || !client_secret) {
      return res.status(400).json({
        success: false,
        message: "client_id and client_secret must be filled",
      });
    }

    const client = await findClientById(client_id);

    if (!client) {
      return res.status(401).json({
        success: false,
        message: "Client not found",
      });
    }

    // client_secret saved as bcrypt hash in db
    const isSecretValid = await bcrypt.compare(
      client_secret,
      client.client_secret,
    );

    if (!isSecretValid) {
      return res.status(401).json({
        success: false,
        message: "client_secret invalid",
      });
    }

    const allowedGrants = (client.grant_types ?? "")
      .split(",")
      .map((g) => g.trim());
    if (!allowedGrants.includes("client_credentials")) {
      return res.status(403).json({
        success: false,
        message: "Client is not allowed to use this grant",
      });
    }

    const accessToken = generateClientToken(client);

    return res.status(200).json({
      success: true,
      access_token: accessToken,
      token_type: "Bearer",
      expires_in: Number(process.env.JWT_ACCESS_EXPIRES_IN),
    });
  } catch (error) {
    console.error("Client credentials grant error:", error);
    return res
      .status(500)
      .json({ success: false, message: "Internal Server Error" });
  }
};

// revoke
export const revoke = async (req, res) => {
  const { refresh_token } = req.body;

  if (!refresh_token) {
    return res.status(400).json({
      success: false,
      message: "refresh_token harus disertakan.",
    });
  }

  try {
    const hashed = hashToken(refresh_token);
    const record = await findRefreshToken(hashed);

    if (!record) {
      // Tetap return 200 — jangan bocorkan info token valid/tidak
      return res
        .status(200)
        .json({ success: true, message: "Token berhasil di-revoke." });
    }

    await revokeRefreshToken(hashed);

    return res
      .status(200)
      .json({ success: true, message: "Token berhasil di-revoke." });
  } catch (error) {
    console.error("Revoke error:", error);
    return res
      .status(500)
      .json({ success: false, message: "Internal Server Error" });
  }
};

// Introspect
export const introspect = async (req, res) => {
  try {
    const { token, token_type_hint } = req.body;

    // Validasi input
    if (!token) {
      return res.status(400).json({
        success: false,
        error: "invalid_request",
        error_description: "Token parameter is required",
      });
    }

    let decoded = null;
    let tokenType = null;
    let isActive = false;
    let response = {
      active: false,
    };

    try {
      decoded = jwt.verify(token, process.env.JWT_ACCESS_SECRET);
      tokenType = "access_token";
      isActive = true;

      // Check if token is revoked (for client credentials)
      if (decoded.jti) {
        const revoked = await isTokenRevoked(decoded.jti);
        if (revoked) {
          isActive = false;
        }
      }

      // Check expiration (already handled by jwt.verify)
      // But double check just in case
      if (decoded.exp && Date.now() >= decoded.exp * 1000) {
        isActive = false;
      }
    } catch (error) {
      if (
        error.name === "JsonWebTokenError" ||
        error.name === "TokenExpiredError"
      ) {
        try {
          const hashed = hashToken(token);
          const refreshRecord = await findRefreshToken(hashed);

          if (refreshRecord) {
            tokenType = "refresh_token";
            isActive = true;

            // Get user info
            const user = await findUserById(refreshRecord.user_id);
            if (user) {
              response = {
                active: true,
                client_id: "refresh_token",
                user_id: user.id,
                email: user.email,
                role: user.role,
                token_type: "refresh_token",
                expires_at: refreshRecord.expires_at,
              };
            }
          }
        } catch (refreshError) {
          isActive = false;
        }
      }
    }

    if (isActive && decoded && tokenType === "access_token") {
      let user = null;
      if (decoded.id) {
        user = await findUserById(decoded.id);
      }

      response = {
        active: true,
        client_id: decoded.client_id || "unknown",
        user_id: decoded.id || null,
        email: decoded.email || user?.email || null,
        role: decoded.role || user?.role || "citizen",
        token_type: "access_token",
        exp: decoded.exp,
        iat: decoded.iat,
        scope: decoded.scope || "basic",
        sub: decoded.sub || decoded.email || null,
      };

      if (decoded.client_id) {
        const client = await findClientById(decoded.client_id);
        if (client) {
          response.client_name = client.client_id;
          response.grant_types = client.grant_types;
        }
      }
    }

    return res.status(200).json(response);
  } catch (error) {
    console.error("Introspection error:", error);
    return res.status(500).json({
      active: false,
      error: "server_error",
      error_description: "Internal server error during token introspection",
    });
  }
};

// Client credentials grant

const grantHandlers = {
  password: passwordGrant,
  refresh_token: refreshTokenGrant,
  client_credentials: clientCredentialsGrant,
};

// Internal handler
const issueTokenPair = async (user) => {
  const accessToken = generateAccessToken(user);
  const refreshToken = generateRefreshToken();
  const hashedRefresh = hashToken(refreshToken);
  const expiredAt = new Date(Date.now() + REFRESH_TOKEN_TTL_MS);

  await insertToken({
    user_id: user.id,
    token_hash: hashedRefresh,
    expires_at: expiredAt,
  });

  return { accessToken, refreshToken };
};
