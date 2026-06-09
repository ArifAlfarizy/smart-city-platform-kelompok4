import { createUser, findUserByEmail } from "../models/userModel.js";
import "dotenv/config";
import bcrypt from "bcrypt";
const saltRounds = Number(process.env.SALT_ROUNDS);

// register
export const register = async (req, res) => {
  try {
    const { name, email, password, photo, oauth_provider, oauth_id, role } =
      req.body;

    // Add validations
    if (!name || !email || !password) {
      return res.status(400).json({
        success: false,
        message: "name, email, and password must be filled",
      });
    }

    // Check existing user
    const checkExistingUser = await findUserByEmail(email);

    if (checkExistingUser) {
      return res.status(400).json({
        // change code to duplicate
        success: false,
        message: "user already exist. Try logging in!",
      });
    }

    const hashedPassword = bcrypt.hashSync(password, saltRounds);

    const newUser = await createUser({
      name,
      email,
      password_hash: hashedPassword,
      photo,
      oauth_provider,
      oauth_id,
    });

    return res.status(201).json({
      success: true,
      message: "Berhasil register",
      data: newUser,
    });
  } catch (error) {
    console.error("Register Error:", error);
    return res.status(500).json({
      success: false,
      message: "Internal Server Error",
    });
  }
};
// login

// logout
