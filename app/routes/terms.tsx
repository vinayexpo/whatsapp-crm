import { Link } from "react-router";
import Box from "@mui/material/Box";
import Paper from "@mui/material/Paper";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import WhatsAppIcon from "@mui/icons-material/WhatsApp";
import type { Route } from "./+types/terms";

export function meta({}: Route.MetaArgs) {
  return [{ title: "Terms & Conditions — Creative Connects" }];
}

const LAST_UPDATED = "September 4, 2026";

export default function Terms() {
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
              Terms &amp; Conditions
            </Typography>
            <Typography variant="body2" color="text.secondary">
              Last updated: {LAST_UPDATED}
            </Typography>
          </Box>

          <Typography variant="body1">
            These Terms &amp; Conditions ("Terms") govern access to and use of the Creative
            Connects customer relationship management platform (the "Service"). By creating an
            account or otherwise using the Service, you agree to these Terms on behalf of
            yourself and, if applicable, the organization you represent ("Company").
          </Typography>

          <Section title="1. Eligibility and Accounts">
            <Body>
              You must be authorized by your Company to create an account. You are responsible
              for maintaining the confidentiality of your login credentials and for all
              activity that occurs under your account. Notify us immediately of any unauthorized
              use of your account.
            </Body>
          </Section>

          <Section title="2. Use of the Service">
            <Body>
              The Service allows a Company to connect its WhatsApp Business and Instagram
              accounts to send and receive messages, manage contacts, organize sales pipelines,
              run campaigns, and use optional AI-assisted and voice calling features. You agree
              to use the Service only for lawful purposes and in compliance with:
            </Body>
            <BulletList
              items={[
                "These Terms and any policies referenced in them",
                "Meta's WhatsApp Business Messaging Policy and Instagram Platform Policy",
                "Applicable data protection, anti-spam, and telecommunications laws",
              ]}
            />
          </Section>

          <Section title="3. Company Responsibilities">
            <Body>
              A Company is responsible for obtaining any consent required from its customers
              before messaging or calling them through the Service, for the accuracy of Contact
              data it stores, and for the conduct of the Agents it authorizes to access its
              account. We are not responsible for the content of messages sent by a Company's
              Agents.
            </Body>
          </Section>

          <Section title="4. Third-Party Platforms">
            <Body>
              The Service depends on third-party platforms, including Meta's WhatsApp and
              Instagram APIs, cloud storage providers, AI service providers, and telephony
              providers. We are not responsible for outages, policy changes, or restrictions
              imposed by these third parties, including suspension of a Company's WhatsApp
              Business or Instagram account by Meta.
            </Body>
          </Section>

          <Section title="5. Acceptable Use">
            <Body>You agree not to use the Service to:</Body>
            <BulletList
              items={[
                "Send unsolicited bulk messages, spam, or content violating Meta's messaging policies",
                "Transmit unlawful, abusive, defamatory, or infringing content",
                "Attempt to gain unauthorized access to the Service or other Companies' data",
                "Reverse engineer, resell, or sublicense the Service without authorization",
                "Interfere with or disrupt the integrity or performance of the Service",
              ]}
            />
          </Section>

          <Section title="6. Data and Privacy">
            <Body>
              Our collection and use of personal information in connection with the Service is
              described in our <Link to="/privacy-policy">Privacy Policy</Link>, which forms
              part of these Terms.
            </Body>
          </Section>

          <Section title="7. Intellectual Property">
            <Body>
              The Service, including its software, design, and branding, is owned by Creative
              Connects and its licensors. These Terms do not grant you any ownership rights in
              the Service. Content you submit (such as Contact data and messages) remains owned
              by you or your Company.
            </Body>
          </Section>

          <Section title="8. Suspension and Termination">
            <Body>
              We may suspend or terminate access to the Service if these Terms are violated, if
              required by a third-party platform provider, or if necessary to protect the
              Service or other users. A Company may stop using the Service at any time.
            </Body>
          </Section>

          <Section title="9. Disclaimers">
            <Body>
              The Service is provided "as is" without warranties of any kind, express or
              implied. We do not guarantee uninterrupted or error-free operation, or that
              messages will always be delivered, given our dependency on third-party messaging
              platforms.
            </Body>
          </Section>

          <Section title="10. Limitation of Liability">
            <Body>
              To the maximum extent permitted by law, Creative Connects shall not be liable for
              any indirect, incidental, special, or consequential damages arising from use of
              the Service, including loss of data, revenue, or business opportunities.
            </Body>
          </Section>

          <Section title="11. Changes to These Terms">
            <Body>
              We may update these Terms from time to time. Continued use of the Service after
              changes take effect constitutes acceptance of the revised Terms.
            </Body>
          </Section>

          <Section title="12. Contact Us">
            <Body>
              If you have questions about these Terms, please contact us at{" "}
              <Box component="span" sx={{ fontWeight: 600 }}>
                support@creativeconnects.web.id
              </Box>
              .
            </Body>
          </Section>

          <Typography variant="body2">
            <Link to="/privacy-policy">Privacy Policy</Link> ·{" "}
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
