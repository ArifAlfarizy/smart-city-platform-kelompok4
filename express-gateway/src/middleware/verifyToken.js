import jwt from "jsonwebtoken";
import "dotenv/config";

const introspectToken = async (token) => {
  try {
    const authServiceUrl =
      process.env.AUTH_SERVICE_URL || "http://localhost:3002";
    const response = await fetch(`${authServiceUrl}/oauth/introspect`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ token }),
    });

    if (!response.ok) {
      return null;
    }

    const data = await response.json();
    return data;
  } catch (error) {
    console.error("[Introspection] Error:", error.message);
    return null;
  }
};

export const verifyToken = async (req, res, next) => {
  try {
    const authHeader = req.headers.authorization;

    if (!authHeader) {
      return res.status(401).json({
        error: "Access denied. No token provided.",
      });
    }

    const token = authHeader.split(" ")[1];

    if (!token) {
      return res.status(401).json({
        error: "Access denied. Invalid token format",
      });
    }

    try {
      const decoded = jwt.verify(token, process.env.JWT_ACCESS_SECRET);
      req.user = decoded;
      return next();
    } catch (localError) {
      console.log(
        "[Gateway] Local JWT verification failed, trying introspection...",
      );

      const introspectResult = await introspectToken(token);

      if (!introspectResult || !introspectResult.active) {
        return res.status(401).json({
          error: "Invalid or expired token.",
        });
      }

      req.user = {
        id: introspectResult.user_id,
        email: introspectResult.email,
        role: introspectResult.role || "citizen",
        client_id: introspectResult.client_id,
        scope: introspectResult.scope,
        exp: introspectResult.exp,
        ...introspectResult,
      };

      return next();
    }
  } catch (error) {
    if (error.name === "TokenExpiredError") {
      return res.status(401).json({
        error: "Token expired. Please login again.",
      });
    }

    if (error.name === "JsonWebTokenError") {
      return res.status(403).json({
        error: "Invalid token.",
      });
    }

    if (error.name === "NotBeforeError") {
      return res.status(401).json({
        error: "JWT not active.",
      });
    }

    console.error("Auth middleware error:", error);
    res.status(500).json({
      error: "Internal server error",
    });
  }
};

export default verifyToken;
