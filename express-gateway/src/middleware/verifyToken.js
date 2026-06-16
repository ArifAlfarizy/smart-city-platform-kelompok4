import jwt from "jsonwebtoken";
import "dotenv/config";

export const verifyToken = (req, res, next) => {
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

    const decoded = jwt.verify(token, process.env.JWT_ACCESS_SECRET);
    req.user = decoded;

    next();
  } catch (error) {
    if (err.name === "TokenExpiredError") {
      return res.status(401).json({
        error: "Token expired. Please login again.",
      });
    }

    if (err.name === "JsonWebTokenError") {
      return res.status(403).json({
        error: "Invalid token.",
      });
    }

    if (err.name === "NotBeforeError") {
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
