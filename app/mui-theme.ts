import { createTheme } from "@mui/material/styles";

export const theme = createTheme({
  palette: {
    mode: "light",
    primary: {
      main: "#00A884",
      light: "#3FC9A8",
      dark: "#00785F",
      contrastText: "#FFFFFF",
    },
    secondary: {
      main: "#7C4DFF",
      light: "#A578FF",
      dark: "#5A2FD4",
      contrastText: "#FFFFFF",
    },
    background: {
      default: "#F4F7F6",
      paper: "#FFFFFF",
    },
    text: {
      primary: "#1B2B29",
      secondary: "#5C6F6B",
    },
    divider: "#E3EBE9",
    success: {
      main: "#2FB673",
    },
    warning: {
      main: "#F2A93B",
    },
    error: {
      main: "#E5484D",
    },
    info: {
      main: "#3B82C4",
    },
  },
  shape: {
    borderRadius: 10,
  },
  typography: {
    fontFamily: '"Manrope", "Helvetica", "Arial", sans-serif',
    h1: { fontFamily: '"Space Grotesk", "Manrope", sans-serif', fontWeight: 700 },
    h2: { fontFamily: '"Space Grotesk", "Manrope", sans-serif', fontWeight: 700 },
    h3: { fontFamily: '"Space Grotesk", "Manrope", sans-serif', fontWeight: 700 },
    h4: { fontFamily: '"Space Grotesk", "Manrope", sans-serif', fontWeight: 700 },
    h5: { fontFamily: '"Space Grotesk", "Manrope", sans-serif', fontWeight: 700 },
    h6: { fontFamily: '"Space Grotesk", "Manrope", sans-serif', fontWeight: 700 },
    button: { textTransform: "none", fontWeight: 600 },
  },
  components: {
    MuiButton: {
      styleOverrides: {
        root: {
          borderRadius: 8,
        },
      },
    },
    MuiPaper: {
      styleOverrides: {
        root: {
          backgroundImage: "none",
        },
      },
    },
    MuiChip: {
      styleOverrides: {
        root: {
          fontWeight: 600,
        },
      },
    },
    MuiAppBar: {
      styleOverrides: {
        root: {
          boxShadow: "none",
        },
      },
    },
  },
});
