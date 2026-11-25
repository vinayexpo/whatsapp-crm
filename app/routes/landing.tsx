import { useState, type KeyboardEvent } from "react";
import { Link } from "react-router";
import ArrowForwardRounded from "@mui/icons-material/ArrowForwardRounded";
import ArrowOutwardRounded from "@mui/icons-material/ArrowOutwardRounded";
import AutoAwesomeRounded from "@mui/icons-material/AutoAwesomeRounded";
import WhatsApp from "@mui/icons-material/WhatsApp";
import Instagram from "@mui/icons-material/Instagram";
import CheckRounded from "@mui/icons-material/CheckRounded";
import DoneAllRounded from "@mui/icons-material/DoneAllRounded";
import AccountTreeRounded from "@mui/icons-material/AccountTreeRounded";
import ChatBubbleOutlineRounded from "@mui/icons-material/ChatBubbleOutlineRounded";
import CampaignRounded from "@mui/icons-material/CampaignRounded";
import PeopleOutlineRounded from "@mui/icons-material/PeopleOutlineRounded";
import StorefrontRounded from "@mui/icons-material/StorefrontRounded";
import BarChartRounded from "@mui/icons-material/BarChartRounded";
import GraphicEqRounded from "@mui/icons-material/GraphicEqRounded";
import AddRounded from "@mui/icons-material/AddRounded";
import ShieldOutlined from "@mui/icons-material/ShieldOutlined";
import s from "./landing.module.css";
import i from "./industries.module.css";
import { PublicHeader, PublicFooter } from "~/components/public/public-shell";
import { industries } from "~/data/industries";
import { WorkflowGraphic } from "~/components/public/workflow-motion";

export function meta() {
  return [
    { title: "Creative Connects — Your AI team. Always connected." },
    {
      name: "description",
      content:
        "Bring AI replies, voice agents, WhatsApp and Instagram conversations, automated workflows, campaigns, CRM and commerce into one connected workspace.",
    },
    { property: "og:title", content: "Creative Connects — Your AI team. Always connected." },
    { property: "og:description", content: "Conversations, AI and automation. Working together." },
  ];
}

const heroModes = ["AI replies", "Voice agents", "Human handoff"] as const;
const heroWorkflows = [
  ["A customer sends a question", "AI uses your business knowledge", "Your team can continue the conversation"],
  ["A customer makes an enquiry", "Your configured agent guides the call", "Your team reviews the conversation"],
  ["A customer asks for help", "AI keeps the conversation context", "A team member takes it from here"],
] as const;
const examples = [
  {
    question: "What are your opening hours?",
    answer: "Our demo store is open Monday–Saturday, 9 am–7 pm. How can I help you today?",
    note: "Answered from business knowledge",
  },
  {
    question: "Can I see your catalog?",
    answer:
      "Of course! Browse our collection in the catalog. Tell me what you’re looking for and I’ll help you find it.",
    note: "Product discovery, inside the conversation",
  },
  {
    question: "I’d like to speak to someone.",
    answer: "Absolutely. I’ll pass your conversation to the team so you can pick up right where we left off.",
    note: "A smooth transition to your team",
  },
];

const features = [
  {
    name: "Shared inbox",
    icon: ChatBubbleOutlineRounded,
    title: "Two channels. One conversation hub.",
    copy: "Bring WhatsApp and Instagram messages together. Give every reply the context of contact details, tags, assignments and conversation history.",
    bullets: [
      "Live messages and delivery status",
      "Media, attachments and team assignments",
      "Contact context beside every conversation",
    ],
  },
  {
    name: "Campaigns",
    icon: CampaignRounded,
    title: "Reach the right people. Make it personal.",
    copy: "Turn your phonebook into a focused audience. Create template-based campaigns, schedule sends and follow delivery, reads and replies.",
    bullets: [
      "CSV and Excel phonebook imports",
      "Phonebook and tag-based audiences",
      "Templates, scheduling and campaign reports",
    ],
  },
  {
    name: "CRM & pipeline",
    icon: PeopleOutlineRounded,
    title: "Keep the next step in sight.",
    copy: "Connect contacts and conversations to your sales pipeline. Track lead stages and deal value so the team knows where to focus next.",
    bullets: [
      "Organized contacts and tags",
      "Visual stages for your sales process",
      "Deal values and activity context",
    ],
  },
  {
    name: "Commerce",
    icon: StorefrontRounded,
    title: "From a message to an order.",
    copy: "Keep products, branch operations and orders in the same workspace as your customers. Manage your catalog and track the order journey.",
    bullets: [
      "Product catalog and inventory",
      "Branches, delivery zones and payments",
      "Order management and commerce reports",
    ],
  },
  {
    name: "Analytics",
    icon: BarChartRounded,
    title: "See how the work adds up.",
    copy: "Review conversations, campaign outcomes and your pipeline together. Give your team a clearer view of activity and results.",
    bullets: [
      "Conversation and contact overview",
      "Campaign delivery and engagement",
      "Pipeline and commerce reporting",
    ],
  },
];

const journeys = [
  {
    name: "Welcome a new lead",
    trigger: "A customer says “hello”",
    steps: [
      "Start a welcome flow",
      "Offer quick-reply choices",
      "Collect the customer’s interest",
      "Continue with the right team",
    ],
    result: "A useful first reply. A clear next step.",
  },
  {
    name: "Help a shopper",
    trigger: "A customer asks about products",
    steps: [
      "Open a shopping menu",
      "Let them browse the catalog",
      "Guide their product selection",
      "Bring the team in when needed",
    ],
    result: "Product discovery stays in the conversation.",
  },
  {
    name: "Guide a support request",
    trigger: "A customer needs help",
    steps: [
      "Recognize the trigger keyword",
      "Show relevant menu options",
      "Answer with business knowledge",
      "Hand over to a team member",
    ],
    result: "Let automation handle the familiar questions.",
  },
];

const faqs = [
  [
    "What can I automate?",
    "Create keyword-triggered workflows, button and list menus, and chatbot conversations. Configure AI assistance with business knowledge, and use voice agents for call workflows. Your available actions depend on the channels and integrations configured for your account.",
  ],
  [
    "Can my team take over from AI?",
    "Yes. Your team can manage conversations in the shared inbox, use contact context and assignments, and respond personally when a conversation needs a human.",
  ],
  [
    "Does it work with WhatsApp and Instagram?",
    "Creative Connects supports WhatsApp and Instagram messaging. WhatsApp campaigns, templates and calling require the relevant connected business account and channel configuration.",
  ],
  [
    "Can I bring my existing contacts?",
    "Yes. Import CSV or Excel (.xlsx) files into phonebooks, organize your contacts, and use phonebooks or tags to choose campaign audiences.",
  ],
  [
    "How do I get started?",
    "If you already have an account, sign in to your workspace. Your administrator manages account access, team roles, channel connections and AI settings. AI, voice and messaging features require their respective integrations.",
  ],
];

function HeroDemo() {
  const [mode, setMode] = useState(0);
  const [example, setExample] = useState(0);
  const chosen = examples[example];
  function moveTab(event: KeyboardEvent<HTMLButtonElement>, index: number) {
    const next =
      event.key === "ArrowRight"
        ? (index + 1) % 3
        : event.key === "ArrowLeft"
          ? (index + 2) % 3
          : event.key === "Home"
            ? 0
            : event.key === "End"
              ? 2
              : null;
    if (next === null) return;
    event.preventDefault();
    setMode(next);
    document.getElementById(`ai-tab-${next}`)?.focus();
  }
  return (
    <div className={s.heroScene}>
      <div className={s.demoWindow}>
        <div className={s.windowBar}>
          <span className={s.windowDots} aria-hidden="true">
            <i />
            <i />
            <i />
          </span>
          <span>YOUR CONNECTED WORKSPACE</span>
          <span className={s.demoLabel}>Interactive demo</span>
        </div>
        <div className={s.demoTabs} role="tablist" aria-label="Explore AI capabilities">
          {heroModes.map((name, i) => (
            <button
              key={name}
              id={`ai-tab-${i}`}
              role="tab"
              aria-selected={mode === i}
              aria-controls={`ai-panel-${i}`}
              tabIndex={mode === i ? 0 : -1}
              onKeyDown={(e) => moveTab(e, i)}
              onClick={() => {
                setMode(i);
              }}
            >
              {name}
            </button>
          ))}
        </div>
        <div
          className={s.demoPanel}
          id={`ai-panel-${mode}`}
          role="tabpanel"
          aria-labelledby={`ai-tab-${mode}`}
          key={mode}
          tabIndex={0}
        >
          {mode === 0 ? (
            <>
              <div className={s.chatHeader}>
                <span className={s.avatar}>AK</span>
                <div>
                  <strong>Aanya Kapoor</strong>
                  <span>
                    <WhatsApp /> WhatsApp conversation
                  </span>
                </div>
                <span className={s.aiPill}>
                  <AutoAwesomeRounded /> AI assistant
                </span>
              </div>
              <div className={s.chatBody} aria-live="polite" aria-atomic="true" key={example}>
                <span className={s.timeLabel}>TODAY · SAMPLE CONVERSATION</span>
                <div className={s.incoming}>
                  {chosen.question}
                  <span>10:42 am</span>
                </div>
                <div className={s.outgoing}>
                  <span className={s.aiAuthor}>
                    <AutoAwesomeRounded /> Creative AI
                  </span>
                  {chosen.answer}
                  <span>
                    10:42 am <DoneAllRounded />
                  </span>
                </div>
                <div className={s.chatNote}>
                  <ShieldOutlined />
                  {chosen.note}
                </div>
              </div>
              <div className={s.promptArea}>
                <span>Try a customer question</span>
                <div>
                  {["Opening hours", "Browse products", "Talk to the team"].map((label, i) => (
                    <button key={label} aria-pressed={example === i} onClick={() => setExample(i)}>
                      {label}
                      <ArrowOutwardRounded />
                    </button>
                  ))}
                </div>
              </div>
            </>
          ) : mode === 1 ? (
            <div className={s.voiceDemo}>
              <span className={s.voiceIcon}>
                <GraphicEqRounded />
              </span>
              <h3>A voice for your business.</h3>
              <p>AI voice agents, call workflows and conversation transcripts.</p>
              <div className={s.wave} aria-hidden="true">
                {Array.from({ length: 29 }, (_, i) => (
                  <i key={i} style={{ height: `${14 + ((i * 17) % 49)}px`, animationDelay: `${i * 0.045}s` }} />
                ))}
              </div>
              <p className={s.voiceTranscript}>
                “Hi, thanks for calling. I can help with your enquiry and connect you to our team.”
              </p>
              <p>This visual demo has no audio and places no calls.</p>
            </div>
          ) : (
            <div className={s.handoffDemo}>
              <div className={s.handoffAvatars}>
                <span className={s.robotAvatar}>
                  <AutoAwesomeRounded />
                </span>
                <ArrowForwardRounded />
                <span className={s.avatar}>RM</span>
              </div>
              <h3>AI starts. Your team takes it further.</h3>
              <p>Keep the conversation moving with a personal reply when it matters.</p>
              <div className={s.handoffRecord}>
                <span>CONVERSATION CONTEXT</span>
                <strong>Aanya needs help choosing a product.</strong>
                <div>
                  <CheckRounded /> Conversation history available
                </div>
                <div>
                  <CheckRounded /> Contact details in the inbox
                </div>
                <div>
                  <CheckRounded /> Ready for a team member
                </div>
              </div>
            </div>
          )}
        </div>
        <WorkflowGraphic
          key={`${mode}-${example}`}
          label="Connected workspace illustrative workflow"
          title="From the first question to the next step."
          steps={heroWorkflows[mode]}
          compact
        />
        <div className={s.demoFooter}>
          <span>
            <i /> Connected by design
          </span>
          <span>Illustrative preview · sample data</span>
        </div>
      </div>
      <div className={s.floatingNote}>
        <span className={s.noteIcon}>
          <AccountTreeRounded />
        </span>
        <div>
          <strong>A conversation. A next step.</strong>
          <span>AI + automation + your team</span>
        </div>
        <span className={s.noteCheck}>
          <CheckRounded />
        </span>
      </div>
    </div>
  );
}

function FeaturePreview({ active }: { active: number }) {
  if (active === 0)
    return (
      <div className={s.inboxPreview}>
        <div className={s.previewHeading}>
          <strong>Team inbox</strong>
          <span className={s.smallTag}>3 sample conversations</span>
        </div>
        {[
          ["AK", "Aanya Kapoor", "Thanks, that’s exactly what I needed.", "WhatsApp", "Now"],
          ["JL", "Jamie Lee", "Is this available in another colour?", "Instagram", "2 min"],
          ["NS", "Neha Shah", "I’d like to know more about your services.", "WhatsApp", "8 min"],
        ].map(([initials, name, message, channel, time], i) => (
          <div className={s.inboxRow} key={name}>
            <span className={`${s.avatar} ${i === 1 ? s.purpleAvatar : ""}`}>{initials}</span>
            <div>
              <strong>
                {name}
                <span>{time}</span>
              </strong>
              <p>{message}</p>
              <span className={s.channelLabel}>
                {channel === "Instagram" ? <Instagram /> : <WhatsApp />}
                {channel}
              </span>
            </div>
          </div>
        ))}
        <div className={s.previewBottom}>
          <DoneAllRounded /> All the context. Right where you reply.
        </div>
      </div>
    );
  if (active === 1)
    return (
      <div className={s.campaignPreview}>
        <div className={s.previewHeading}>
          <strong>Weekend collection</strong>
          <span className={s.smallTag}>Sample campaign</span>
        </div>
        <div className={s.campaignEnvelope}>
          <CampaignRounded />
          <span>Something new. Just for you.</span>
          <p>Bring your next collection to the people who want to hear about it.</p>
          <span className={s.campaignLink}>
            View collection <ArrowForwardRounded />
          </span>
        </div>
        <div className={s.campaignSteps}>
          {["Choose a phonebook", "Select a template", "Schedule your send"].map((t, i) => (
            <span key={t}>
              <b>{i + 1}</b>
              {t}
              <CheckRounded />
            </span>
          ))}
        </div>
      </div>
    );
  if (active === 2)
    return (
      <div>
        <div className={s.previewHeading}>
          <strong>Your next opportunity</strong>
          <span className={s.smallTag}>Sample pipeline</span>
        </div>
        <div className={s.pipelinePreview}>
          {["New lead", "In conversation", "Won"].map((stage, i) => (
            <div key={stage}>
              <span>
                <i />
                {stage}
              </span>
              <article>
                <span>{["AK", "JL", "NS"][i]}</span>
                <strong>{["Aanya Kapoor", "Jamie Lee", "Neha Shah"][i]}</strong>
                <p>{["Product enquiry", "Consultation follow-up", "Order confirmed"][i]}</p>
                <small>{["WhatsApp", "Instagram", "WhatsApp"][i]}</small>
              </article>
            </div>
          ))}
        </div>
        <div className={s.previewBottom}>
          <PeopleOutlineRounded /> From first hello to the next milestone.
        </div>
      </div>
    );
  if (active === 3)
    return (
      <div>
        <div className={s.previewHeading}>
          <strong>Connected commerce</strong>
          <span className={s.smallTag}>Sample order</span>
        </div>
        <div className={s.orderPreview}>
          <div className={s.productArt} aria-hidden="true">
            <StorefrontRounded />
          </div>
          <div>
            <span>ORDER #CC-2048</span>
            <h3>Everyday essentials</h3>
            <p>2 items · Central branch</p>
            <span className={s.smallTag}>Confirmed</span>
          </div>
        </div>
        <div className={s.orderSteps}>
          {["Order received", "Preparing", "Ready for delivery"].map((t, i) => (
            <div key={t}>
              <span>{i === 0 ? <CheckRounded /> : i + 1}</span>
              {t}
            </div>
          ))}
        </div>
        <div className={s.previewBottom}>Catalog → conversation → order</div>
      </div>
    );
  return (
    <div>
      <div className={s.previewHeading}>
        <strong>A clearer picture</strong>
        <span className={s.smallTag}>Illustrative data</span>
      </div>
      <div className={s.chartPreview}>
        <span>Conversation activity</span>
        <div role="img" aria-label="Illustrative weekly conversation activity bar chart; no real business data">
          {[34, 56, 45, 78, 64, 88, 70].map((height, i) => (
            <span key={i}>
              <i style={{ height: `${height}%` }} />
              <small>{["M", "T", "W", "T", "F", "S", "S"][i]}</small>
            </span>
          ))}
        </div>
      </div>
      <div className={s.reportLabels}>
        <span>
          Campaigns
          <CheckRounded />
        </span>
        <span>
          Pipeline
          <CheckRounded />
        </span>
        <span>
          Orders
          <CheckRounded />
        </span>
      </div>
    </div>
  );
}

export default function Landing() {
  const [feature, setFeature] = useState(0);
  const [journey, setJourney] = useState(0);
  const current = features[feature];
  const flow = journeys[journey];
  function selectFeature(event: KeyboardEvent<HTMLButtonElement>, i: number) {
    const n =
      event.key === "ArrowDown" || event.key === "ArrowRight"
        ? (i + 1) % features.length
        : event.key === "ArrowUp" || event.key === "ArrowLeft"
          ? (i + features.length - 1) % features.length
          : event.key === "Home"
            ? 0
            : event.key === "End"
              ? features.length - 1
              : null;
    if (n === null) return;
    event.preventDefault();
    setFeature(n);
    document.getElementById(`feature-tab-${n}`)?.focus();
  }
  return (
    <div className={s.page}>
      <a className={s.skipLink} href="#main">
        Skip to content
      </a>
      <PublicHeader />
      <main id="main">
        <section className={`${s.hero} ${s.container}`} aria-labelledby="hero-title">
          <div className={s.heroCopy}>
            <h1 id="hero-title">
              Your AI team.
              <br />
              <span>Always on.</span>
              <br />
              Always connected.
            </h1>
            <p>
              Give every customer a next step. Bring AI replies, voice agents and automated journeys to your WhatsApp
              and Instagram conversations.
            </p>
            <div className={s.heroActions}>
              <Link className={s.primaryButton} to="/login">
                Open your workspace <ArrowForwardRounded />
              </Link>
              <a className={s.textButton} href="#automation">
                <AccountTreeRounded /> See automation in action
              </a>
            </div>
            <div className={s.heroChannels}>
              <span>
                <WhatsApp /> WhatsApp
              </span>
              <span>
                <Instagram /> Instagram
              </span>
              <i />
              <span>One connected workspace</span>
            </div>
          </div>
          <HeroDemo />
        </section>
        <div className={s.capabilityStrip}>
          <div className={s.container}>
            <span>
              Less busywork.
              <br />
              <strong>More connection.</strong>
            </span>
            <span>
              <AutoAwesomeRounded /> AI assistants & chatbots
            </span>
            <span>
              <AccountTreeRounded /> Automated workflows
            </span>
            <span>
              <GraphicEqRounded /> AI voice agents
            </span>
            <span>
              <PeopleOutlineRounded /> Your team, in control
            </span>
          </div>
        </div>

        <section className={`${s.automationSection} ${s.container}`} id="automation" aria-labelledby="automation-title">
          <div className={s.sectionIntro}>
            <h2 id="automation-title">
              Great conversations
              <br />
              don’t happen by accident.
            </h2>
            <p>
              Build the journey behind the reply. Connect triggers, menus and AI assistance so the everyday work keeps
              moving.
            </p>
          </div>
          <div className={s.automationLayout}>
            <div className={s.journeyCopy}>
              <h3>
                A thoughtful next step.
                <br />
                Every time.
              </h3>
              <p>
                Start with a customer’s message. Shape what happens next with visual workflows, chat menus and
                business-trained assistants.
              </p>
              <div className={s.journeyChoices} aria-label="Choose an example workflow">
                {journeys.map((j, i) => (
                  <button key={j.name} aria-pressed={journey === i} onClick={() => setJourney(i)}>
                    <span>{j.name}</span>
                    <ArrowForwardRounded />
                  </button>
                ))}
              </div>
              <span className={s.sampleCaption}>Explore sample journeys. Configure your own in the workspace.</span>
            </div>
            <div className={s.flowCanvas} aria-live="polite" aria-atomic="true">
              <div className={s.flowToolbar}>
                <span>
                  <AccountTreeRounded /> Workflow preview
                </span>
                <span className={s.flowStatus}>Example</span>
              </div>
              <div className={s.flowDiagram} key={journey}>
                <div className={s.triggerNode}>
                  <WhatsApp />
                  <div>
                    <small>WHEN A MESSAGE ARRIVES</small>
                    <strong>{flow.trigger}</strong>
                  </div>
                </div>
                <div className={s.flowLine} aria-hidden="true" />
                <div className={s.flowNodes}>
                  {flow.steps.map((step, i) => (
                    <div key={step}>
                      <span className={i === 2 ? s.flowAi : ""}>
                        {i === 2 ? (
                          <AutoAwesomeRounded />
                        ) : i === 3 ? (
                          <PeopleOutlineRounded />
                        ) : i === 0 ? (
                          <AccountTreeRounded />
                        ) : (
                          <ChatBubbleOutlineRounded />
                        )}
                      </span>
                      <strong>{step}</strong>
                      <CheckRounded />
                    </div>
                  ))}
                </div>
                <div className={s.flowResult}>
                  <CheckRounded />
                  {flow.result}
                </div>
              </div>
            </div>
          </div>
        </section>

        <section className={s.productSection} id="product" aria-labelledby="product-title">
          <div className={s.container}>
            <div className={s.sectionIntro}>
              <h2 id="product-title">
                AI is the beginning.
                <br />
                Connection is the whole point.
              </h2>
              <p>
                Everything around the conversation, in one place. Explore the tools that help your team turn a first
                hello into a lasting relationship.
              </p>
            </div>
            <div className={s.featureLayout}>
              <div className={s.featureTabs} role="tablist" aria-label="Explore the platform">
                {features.map((f, i) => (
                  <button
                    key={f.name}
                    id={`feature-tab-${i}`}
                    role="tab"
                    aria-selected={feature === i}
                    aria-controls="feature-panel"
                    tabIndex={feature === i ? 0 : -1}
                    onKeyDown={(e) => selectFeature(e, i)}
                    onClick={() => setFeature(i)}
                  >
                    <f.icon />
                    <span>{f.name}</span>
                    <ArrowForwardRounded />
                  </button>
                ))}
              </div>
              <div
                id="feature-panel"
                className={s.featureContent}
                role="tabpanel"
                aria-labelledby={`feature-tab-${feature}`}
                tabIndex={0}
              >
                <div className={s.featureDescription} key={current.name}>
                  <h3>{current.title}</h3>
                  <p>{current.copy}</p>
                  <ul>
                    {current.bullets.map((b) => (
                      <li key={b}>
                        <CheckRounded />
                        {b}
                      </li>
                    ))}
                  </ul>
                </div>
                <div className={s.featurePreview} key={feature}>
                  <FeaturePreview active={feature} />
                </div>
              </div>
            </div>
          </div>
        </section>

        <section className={`${s.platformSection} ${s.container}`} id="platform" aria-labelledby="platform-title">
          <div className={s.platformHeading}>
            <h2 id="platform-title">
              One workspace.
              <br />
              So many ways forward.
            </h2>
            <p>From your first AI reply to the day’s last order, your tools belong together.</p>
          </div>
          <div className={s.platformGroups}>
            {[
              {
                icon: AutoAwesomeRounded,
                title: "Let AI do the groundwork",
                text: "Configure your assistant, build chatbots, and use business knowledge to guide replies.",
                items: ["AI assistant", "Channel chatbots", "Knowledge & training", "Voice agents"],
              },
              {
                icon: AccountTreeRounded,
                title: "Build your customer journeys",
                text: "Connect conversations to the next action with flows, menus and targeted campaigns.",
                items: ["Visual automations", "Chat menus & buttons", "Approved templates", "Scheduled campaigns"],
              },
              {
                icon: PeopleOutlineRounded,
                title: "Give your team the full picture",
                text: "Bring conversations, contacts and opportunities into a shared view of the customer.",
                items: ["WhatsApp & Instagram inbox", "Contacts & phonebooks", "CRM pipeline", "WhatsApp calling"],
              },
              {
                icon: StorefrontRounded,
                title: "Keep business moving",
                text: "Manage your commerce operations and review the results from the same workspace.",
                items: [
                  "Branches & catalogs",
                  "Inventory & orders",
                  "Payments & delivery zones",
                  "Analytics & reports",
                ],
              },
            ].map((g) => (
              <article key={g.title}>
                <g.icon />
                <h3>{g.title}</h3>
                <p>{g.text}</p>
                <ul>
                  {g.items.map((i) => (
                    <li key={i}>{i}</li>
                  ))}
                </ul>
              </article>
            ))}
          </div>
          <div className={s.platformFoot}>
            <ShieldOutlined />
            <span>Shared workspace. Role-based access. Connected integrations.</span>
            <Link to="/login">
              Meet your workspace <ArrowOutwardRounded />
            </Link>
          </div>
        </section>

        <section className={`${s.container} ${i.landingSectors}`} aria-labelledby="industries-title">
          <div className={i.sectionIntro}>
            <div>
              <h2 id="industries-title">Your business. Your conversations.</h2>
              <p>Explore practical ways to put AI and automation to work for your team.</p>
            </div>
            <Link to="/industries" className={s.textButton}>
              Explore all industries <ArrowForwardRounded />
            </Link>
          </div>
          <div className={i.landingLinks}>
            {industries.map((industry) => (
              <Link key={industry.slug} to={`/industries/${industry.slug}`}>
                {industry.name}
                <ArrowOutwardRounded />
              </Link>
            ))}
          </div>
        </section>

        <section className={s.faqSection} id="faqs" aria-labelledby="faq-title">
          <div className={`${s.container} ${s.faqLayout}`}>
            <div>
              <h2 id="faq-title">
                A few things
                <br />
                you might be wondering.
              </h2>
              <p>
                Good questions.
                <br />
                Straightforward answers.
              </p>
            </div>
            <div className={s.faqList}>
              {faqs.map(([question, answer]) => (
                <details key={question}>
                  <summary>
                    {question}
                    <AddRounded />
                  </summary>
                  <p>{answer}</p>
                </details>
              ))}
            </div>
          </div>
        </section>
        <section className={`${s.finalSection} ${s.container}`}>
          <div className={s.finalCta}>
            <div>
              <h2>
                Your next conversation
                <br />
                could go further.
              </h2>
              <p>Let AI handle the routine. Give your team room for the meaningful.</p>
            </div>
            <Link className={s.lightButton} to="/login">
              Sign in to Creative Connects <ArrowOutwardRounded />
            </Link>
          </div>
        </section>
      </main>
      <PublicFooter />
    </div>
  );
}
