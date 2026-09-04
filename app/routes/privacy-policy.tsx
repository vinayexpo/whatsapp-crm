import { Link } from "react-router";
import Box from "@mui/material/Box";
import Paper from "@mui/material/Paper";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import WhatsAppIcon from "@mui/icons-material/WhatsApp";
import type { Route } from "./+types/privacy-policy";

export function meta({}: Route.MetaArgs) {
  return [{ title: "Privacy Policy — Creative Connects" }];
}

const LAST_UPDATED = "September 4, 2026";

export default function PrivacyPolicy() {
  return (
    <Box sx={{ minHeight: "100vh", bgcolor: "background.default", py: 6, px: 2 }}>
      <Paper elevation={3} sx={{ p: { xs: 3, sm: 5 }, maxWidth: 820, mx: "auto" }}>
        <Stack spacing={3}>
          <Stack direction="row" spacing={1.25} sx={{ alignItems: "center" }}>
            <Box
              sx={{
                width: 36,
                height: 36,
                borderRadius: "10px",
                bgcolor: "primary.main",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
              }}
            >
              <WhatsAppIcon sx={{ color: "primary.contrastText", fontSize: 20 }} />
            </Box>
            <Typography variant="h6" sx={{ fontWeight: 700 }}>
              Creative Connects
            </Typography>
          </Stack>

          <Box>
            <Typography variant="h4" sx={{ fontWeight: 700 }}>
              Privacy Policy
            </Typography>
            <Typography variant="body2" color="text.secondary">
              Last updated: {LAST_UPDATED}
            </Typography>
          </Box>

          <Typography variant="body1">
            Creative Connects ("we", "us", "our") provides a customer relationship management
            platform that connects businesses with their customers over WhatsApp and Instagram
            (the "Service"). This Privacy Policy explains what information we collect, how we
            use it, and the choices available to you.
          </Typography>

          <Section title="1. Information We Collect">
            <SubHeading>Account Information</SubHeading>
            <Body>
              When your organization ("Company") signs up, we collect the name, email address,
              and password (stored as a secure hash) of each staff member ("Agent") who is
              granted access, along with their role and profile avatar. We also record basic
              session information such as IP address and browser user agent for security
              purposes.
            </Body>
            <SubHeading>Contact Information</SubHeading>
            <Body>
              When an Agent's business communicates with customers, we store the customer's
              ("Contact") name, phone number or Instagram handle, email address (if provided),
              location, avatar, notes added by Agents, purchase history, pipeline/deal stage,
              and the timestamp of their last interaction.
            </Body>
            <SubHeading>Message and Call Content</SubHeading>
            <Body>
              We store the content of messages sent and received via WhatsApp and Instagram,
              including text, delivery status, interactive button replies, and any attached
              media (images, documents, video, or audio). For voice calling features, we store
              call recordings, transcripts, and AI-generated call summaries where that feature
              is enabled by the Company.
            </Body>
            <SubHeading>Notification Data</SubHeading>
            <Body>
              If you enable browser push notifications, we store the push subscription endpoint
              and associated keys needed to deliver notifications to your device.
            </Body>
          </Section>

          <Section title="2. How We Use Information">
            <Body>We use the information described above to:</Body>
            <BulletList
              items={[
                "Operate the Service, including sending and receiving WhatsApp and Instagram messages on behalf of a Company",
                "Display conversations, contacts, and pipeline data to authorized Agents",
                "Store message attachments and call recordings securely",
                "Generate AI-assisted reply suggestions and call summaries where enabled",
                "Send account-related and, where enabled, activity notifications",
                "Maintain the security and integrity of the Service",
              ]}
            />
          </Section>

          <Section title="3. Third-Party Services">
            <Body>We rely on the following third parties to operate the Service:</Body>
            <BulletList
              items={[
                "Meta Platforms, Inc. — WhatsApp Business API and Instagram Messaging API, used to send and receive messages on behalf of a Company's connected accounts.",
                "Amazon Web Services (AWS) — cloud file storage (Amazon S3) for message attachments and media.",
                "AI language model providers (e.g. OpenAI-compatible services) — used, where a Company enables the AI Assistant or voice features, to process conversation or call content in order to generate suggested replies, transcripts, or summaries.",
                "Telephony/voice infrastructure providers — used to place and record calls where WhatsApp Calling or voice agent features are enabled.",
              ]}
            />
            <Body>
              Each of these providers processes data only as necessary to provide their
              respective service to us, under their own applicable privacy and security terms.
            </Body>
          </Section>

          <Section title="4. Data Sharing">
            <Body>
              We do not sell personal information. Data is shared only with the third-party
              service providers described above, and within a Company's own account by its
              authorized Agents. Each Company's data is logically separated from other
              Companies using the Service.
            </Body>
          </Section>

          <Section title="5. Data Retention">
            <Body>
              We retain Contact and conversation data for as long as a Company's account
              remains active, or as needed to provide the Service. Contacts may be deleted by
              an Agent, which marks the record for removal. A Company may request deletion of
              its account and associated data by contacting us using the details below.
            </Body>
          </Section>

          <Section title="6. Data Security">
            <Body>
              We use industry-standard measures to protect data, including encrypted
              connections (HTTPS/TLS), hashed passwords, and access controls restricting data
              to authorized Agents within each Company. No method of transmission or storage is
              completely secure, and we cannot guarantee absolute security.
            </Body>
          </Section>

          <Section title="7. Your Rights">
            <Body>
              Depending on your location, you may have rights to access, correct, or request
              deletion of your personal information. Contacts who wish to exercise these rights
              should contact the Company they interacted with directly, as that Company
              controls its own customer data. Agents may contact their Company administrator or
              us directly.
            </Body>
          </Section>

          <Section title="8. Children's Privacy">
            <Body>
              The Service is intended for business use and is not directed to individuals under
              16. We do not knowingly collect personal information from children.
            </Body>
          </Section>

          <Section title="9. Changes to This Policy">
            <Body>
              We may update this Privacy Policy from time to time. Material changes will be
              reflected by updating the "Last updated" date above.
            </Body>
          </Section>

          <Section title="10. Contact Us">
            <Body>
              If you have questions about this Privacy Policy, please contact us at{" "}
              <Box component="span" sx={{ fontWeight: 600 }}>
                support@creativeconnects.web.id
              </Box>
              .
            </Body>
          </Section>

          <Typography variant="body2">
            <Link to="/terms">Terms &amp; Conditions</Link> ·{" "}
            <Link to="/login">Back to Sign in</Link>
          </Typography>
        </Stack>
      </Paper>
    </Box>
  );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <Box>
      <Typography variant="h6" sx={{ fontWeight: 700, mb: 1 }}>
        {title}
      </Typography>
      <Stack spacing={1.25}>{children}</Stack>
    </Box>
  );
}

function SubHeading({ children }: { children: React.ReactNode }) {
  return (
    <Typography variant="subtitle2" sx={{ fontWeight: 700, mt: 1 }}>
      {children}
    </Typography>
  );
}

function Body({ children }: { children: React.ReactNode }) {
  return (
    <Typography variant="body1" color="text.secondary">
      {children}
    </Typography>
  );
}

function BulletList({ items }: { items: string[] }) {
  return (
    <Box component="ul" sx={{ m: 0, pl: 3 }}>
      {items.map((item) => (
        <Typography key={item} component="li" variant="body1" color="text.secondary">
          {item}
        </Typography>
      ))}
    </Box>
  );
}
