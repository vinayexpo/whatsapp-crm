import { Fragment, type ReactNode } from "react";

const TOKEN_PATTERN = /(\*[^*\n]+\*|_[^_\n]+_|~[^~\n]+~|```[^`]+```|`[^`\n]+`)/g;

function renderInline(text: string, keyPrefix: string): ReactNode[] {
  const parts = text.split(TOKEN_PATTERN);
  return parts
    .filter((part) => part !== "")
    .map((part, index) => {
      const key = `${keyPrefix}-${index}`;
      if (part.startsWith("*") && part.endsWith("*") && part.length > 1) {
        return <strong key={key}>{part.slice(1, -1)}</strong>;
      }
      if (part.startsWith("_") && part.endsWith("_") && part.length > 1) {
        return <em key={key}>{part.slice(1, -1)}</em>;
      }
      if (part.startsWith("~") && part.endsWith("~") && part.length > 1) {
        return <s key={key}>{part.slice(1, -1)}</s>;
      }
      if (part.startsWith("```") && part.endsWith("```") && part.length > 5) {
        return <code key={key}>{part.slice(3, -3)}</code>;
      }
      if (part.startsWith("`") && part.endsWith("`") && part.length > 1) {
        return <code key={key}>{part.slice(1, -1)}</code>;
      }
      return <Fragment key={key}>{part}</Fragment>;
    });
}

/** Renders WhatsApp-style formatted text (*bold*, _italic_, ~strike~, `code`) preserving line breaks. */
export function WhatsappText({ text }: { text: string }) {
  const lines = text.split("\n");
  return (
    <>
      {lines.map((line, index) => (
        <Fragment key={index}>
          {index > 0 && <br />}
          {renderInline(line, String(index))}
        </Fragment>
      ))}
    </>
  );
}
