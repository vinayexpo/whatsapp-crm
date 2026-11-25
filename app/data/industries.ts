export type IndustryScenario = {
  label: string;
  question: string;
  answer: string;
  steps: [string, string, string];
  feature: string;
  detail: string;
};
export type Industry = {
  slug: string;
  name: string;
  audience: string;
  headline: string;
  description: string;
  summary: string;
  contact: string;
  team: string;
  scenarios: [IndustryScenario, IndustryScenario, IndustryScenario];
  setup: [string, string, string];
  boundary: string;
  faq: [string, string];
};

const existingIndustries: Industry[] = [
  {
    slug: "education-edtech",
    name: "Education & Edtech",
    audience: "Schools, colleges, course providers & edtech teams",
    headline: "Help every learner find their next step.",
    description:
      "Answer course and admissions questions with AI, guide learner enquiries and keep your education team connected in one inbox.",
    summary: "Admissions, course enquiries and learner communication.",
    contact: "Learner enquiry",
    team: "Admissions team",
    scenarios: [
      {
        label: "Admissions questions",
        question: "How do I apply for your next course intake?",
        answer:
          "I can help with the application process. Which course or programme interests you? Our admissions team can confirm eligibility, availability and next steps.",
        steps: ["Answer from course information", "Identify the admission enquiry", "Continue with admissions"],
        feature: "AI assistant & shared inbox",
        detail:
          "Teach the assistant your course details, admissions process and office hours. Let your team confirm eligibility, availability and individual decisions.",
      },
      {
        label: "Learner support",
        question: "Where can I ask about joining my online class?",
        answer:
          "Please choose the help you need: course information, joining instructions or speaking with learner support. The team can help with your specific access question.",
        steps: ["Open a learner help menu", "Choose the enquiry topic", "Connect with learner support"],
        feature: "Chat menus & team assignments",
        detail:
          "Give students and parents a clear path to common information, and let staff continue the conversation when an answer needs personal attention.",
      },
      {
        label: "Course updates",
        question: "Can you share information about the open day?",
        answer:
          "The education team can share open-day details and answer your questions here. Would you like information about the programme or the admissions process?",
        steps: ["Organize interested contacts", "Prepare an approved template", "Schedule an open-day campaign"],
        feature: "Phonebooks & campaigns",
        detail:
          "Organize interested learners and parents into phonebooks and schedule template-based course or open-day updates for eligible, opted-in recipients.",
      },
    ],
    setup: [
      "Add course FAQs and admissions information.",
      "Build menus for admissions and learner support.",
      "Connect your team and organize learner audiences.",
    ],
    boundary:
      "Your team configures course information and messaging workflows. Learning-platform access, student records, attendance and fee collection remain part of your existing education systems.",
    faq: [
      "Can AI make admissions decisions?",
      "The assistant can explain information you provide and help with common questions. Your education team confirms availability, eligibility and admissions decisions.",
    ],
  },
  {
    slug: "healthcare",
    name: "Healthcare",
    audience: "Clinics, hospitals & patient support teams",
    headline: "Let reception focus on the people in front of them.",
    description:
      "Use AI for service information, guide appointment enquiries and bring your reception team into conversations that need a personal reply.",
    summary: "Service FAQs, appointment enquiries and reception support.",
    contact: "Patient enquiry",
    team: "Reception team",
    scenarios: [
      {
        label: "Service information",
        question: "Where is your centre and when is reception open?",
        answer: "I can share the service information provided by reception. Which location would you like to visit?",
        steps: ["Recognize an information request", "Answer from approved FAQs", "Offer reception assistance"],
        feature: "AI business knowledge",
        detail:
          "Make approved opening hours, directions and service information available in replies without asking reception to repeat them each time.",
      },
      {
        label: "Appointment enquiries",
        question: "Can I request an appointment for Friday?",
        answer:
          "Reception can help with that request. Which service are you looking for? The team will confirm available times with you.",
        steps: [
          "Identify an appointment request",
          "Guide the enquiry with a menu",
          "Let reception confirm availability",
        ],
        feature: "Chat menus & human handoff",
        detail:
          "Give appointment enquiries a consistent starting point. Reception checks availability and confirms arrangements through the shared inbox.",
      },
      {
        label: "Talk to reception",
        question: "I need to speak with the front desk.",
        answer:
          "Of course. You can continue this conversation with reception, who can help with your administrative enquiry.",
        steps: ["Recognize a request for staff", "Keep the conversation context", "Assign the reception team"],
        feature: "Shared inbox & assignments",
        detail:
          "Let staff see the existing conversation when they take over, so visitors can continue their administrative enquiry in the same thread.",
      },
    ],
    setup: [
      "Add approved reception hours, locations and service FAQs.",
      "Create menus for administrative enquiries.",
      "Assign reception staff to follow up personally.",
    ],
    boundary:
      "Examples cover administrative communication, not diagnosis or medical advice. Appointment availability must be confirmed by staff; no clinical-record integration or healthcare compliance certification is implied.",
    faq: [
      "Does this replace healthcare scheduling software?",
      "These workflows help reception collect and respond to appointment enquiries. They do not imply a built-in medical scheduling system or clinical-record integration.",
    ],
  },
  {
    slug: "restaurants",
    name: "Restaurants",
    audience: "Restaurant owners & service teams",
    headline: "Turn menu questions into the next order.",
    description:
      "Help guests explore your menu with AI, guide ordering conversations and manage the order journey alongside your customer messages.",
    summary: "Menu discovery, ordering conversations and guest support.",
    contact: "Guest enquiry",
    team: "Restaurant team",
    scenarios: [
      {
        label: "Menu questions",
        question: "Do you have vegetarian options?",
        answer:
          "I can help you explore the menu. Would you like to browse vegetarian dishes or speak with the restaurant team about a specific requirement?",
        steps: ["Understand the menu question", "Share configured menu information", "Offer the catalog or team"],
        feature: "AI assistant & product catalog",
        detail:
          "Use your configured business knowledge and product catalog to help guests discover dishes. Staff should confirm allergies and special requirements.",
      },
      {
        label: "Ordering",
        question: "I would like to order for delivery.",
        answer:
          "You can start by browsing the menu. Choose your items, and the restaurant can review your order and delivery details through the configured ordering flow.",
        steps: ["Open the ordering menu", "Choose catalog items", "Manage the order in your workspace"],
        feature: "Chat menus & commerce",
        detail:
          "Connect configured shopping menus with your catalog, branches and order management. Set delivery zones and payment options for your operation.",
      },
      {
        label: "Guest support",
        question: "Can someone help with my order?",
        answer:
          "Yes. Please share your order reference with the restaurant team here, and they can check the details and help you.",
        steps: ["Recognize an order enquiry", "Keep messages in one inbox", "Let staff review the order"],
        feature: "Shared inbox & orders",
        detail:
          "Keep guest conversations and commerce operations in the same workspace, so your staff can review an order and respond personally.",
      },
    ],
    setup: [
      "Add menu information and common guest questions.",
      "Configure your catalog, branches and ordering menus.",
      "Connect service staff and delivery settings.",
    ],
    boundary:
      "Ordering, delivery and payments depend on your configured commerce flow and connected services. Your team confirms special dietary requirements and order changes.",
    faq: [
      "Can I use this for multiple restaurant branches?",
      "The application includes branch and commerce management. Configure the catalog, inventory, delivery zones and staff access for your operation before enabling an ordering flow.",
    ],
  },
  {
    slug: "real-estate",
    name: "Real estate",
    audience: "Property agents & sales teams",
    headline: "Keep the conversation going after the first enquiry.",
    description:
      "Answer property FAQs with AI, organize buyer interest and give your agents the context to follow up on WhatsApp and Instagram.",
    summary: "Property enquiries, buyer interests and sales follow-up.",
    contact: "Buyer enquiry",
    team: "Property team",
    scenarios: [
      {
        label: "Property questions",
        question: "Do you have information about the Riverside project?",
        answer:
          "I can help with the project information shared by the property team. Are you interested in the location, property types or speaking with an agent?",
        steps: ["Recognize the project enquiry", "Answer from supplied property FAQs", "Offer an agent conversation"],
        feature: "AI business knowledge",
        detail:
          "Add approved project information and common buying questions to your assistant. Agents confirm live availability, pricing and terms.",
      },
      {
        label: "Buyer interests",
        question: "I am looking for a two-bedroom home.",
        answer:
          "Would you like to discuss a preferred area or speak to an agent? The team can review your requirements and suggest the next step.",
        steps: ["Guide the buyer with a menu", "Organize the contact with tags", "Track the lead in your pipeline"],
        feature: "Chat menus, tags & pipeline",
        detail:
          "Use menus and contact organization to give enquiries a clear path, then track lead stages and deal context in your sales pipeline.",
      },
      {
        label: "Visit enquiries",
        question: "Can I arrange a site visit this weekend?",
        answer:
          "An agent can help arrange that. Please share your preferred day in this conversation; the property team will confirm the visit details.",
        steps: [
          "Identify a site-visit request",
          "Assign an available team member",
          "Let the agent confirm arrangements",
        ],
        feature: "Shared inbox & assignments",
        detail:
          "Keep visit requests with the conversation history and assign an agent to follow up. Confirm schedules personally with the buyer.",
      },
    ],
    setup: [
      "Add approved project FAQs and business information.",
      "Create menus and tags for buyer enquiries.",
      "Set pipeline stages and agent assignments.",
    ],
    boundary:
      "Agents confirm property availability, pricing and visits. The product provides messaging and CRM workflows, not a built-in property listing feed or site-visit calendar.",
    faq: [
      "Will AI know which properties are still available?",
      "The assistant uses the information your team configures. An agent must verify current availability, prices and transaction details; no live listing-feed integration is implied.",
    ],
  },
  {
    slug: "spas-salons",
    name: "Spas & Salons",
    audience: "Spa, salon & front-desk teams",
    headline: "Welcome the next client before they walk in.",
    description:
      "Answer spa and salon service questions with AI, guide appointment requests and keep your team connected to clients in one inbox.",
    summary: "Spa and salon services, appointment enquiries and client updates.",
    contact: "Client enquiry",
    team: "Spa and salon team",
    scenarios: [
      {
        label: "Service questions",
        question: "What spa and salon services do you offer?",
        answer:
          "I can share the service information provided by the team. Are you interested in spa treatments, hair services or speaking with someone about a consultation?",
        steps: ["Understand the service question", "Answer from salon information", "Guide the client to the team"],
        feature: "AI assistant & chat menus",
        detail:
          "Teach your assistant spa and salon service descriptions, opening hours and FAQs so clients can find a useful starting point for their enquiry.",
      },
      {
        label: "Appointment requests",
        question: "Can I request a treatment on Saturday?",
        answer:
          "The salon team can check that for you. Please share your preferred time here, and they will confirm the appointment with you.",
        steps: [
          "Recognize an appointment enquiry",
          "Keep the client's request in context",
          "Let the salon confirm a time",
        ],
        feature: "Shared inbox & human handoff",
        detail:
          "Make it easy for front-desk staff to continue appointment conversations. Your team checks practitioner availability and confirms the booking.",
      },
      {
        label: "Client updates",
        question: "How can I hear about new salon services?",
        answer:
          "The salon team can help you choose whether to receive service updates. Would you like to speak with them about the options?",
        steps: ["Organize opted-in clients", "Prepare an approved message", "Schedule a focused campaign"],
        feature: "Phonebooks & campaigns",
        detail:
          "Use contact groups and scheduled template campaigns to share new services or seasonal offers with eligible clients who want to hear from you.",
      },
    ],
    setup: [
      "Add treatment details, hours and service FAQs.",
      "Create menus for service and appointment enquiries.",
      "Connect staff and organize opted-in client groups.",
    ],
    boundary:
      "Appointment requests are confirmed by your staff. No built-in stylist calendar or automatic appointment booking is implied.",
    faq: [
      "Can the assistant book a stylist automatically?",
      "It can guide a client's appointment enquiry and pass the conversation to your salon team. Staff confirm the stylist and time using your existing booking process.",
    ],
  },
  {
    slug: "gyms",
    name: "Gyms",
    audience: "Gym owners & membership teams",
    headline: "Help new members take the first step.",
    description:
      "Use AI to answer membership questions, guide tour enquiries and keep your team ready to continue every promising conversation.",
    summary: "Membership FAQs, tour enquiries and member communication.",
    contact: "Membership enquiry",
    team: "Membership team",
    scenarios: [
      {
        label: "Membership questions",
        question: "What membership options do you have?",
        answer:
          "I can help with the membership information shared by the gym. Are you interested in facilities, opening hours or speaking to the membership team?",
        steps: ["Identify the membership question", "Answer from gym information", "Offer the membership team"],
        feature: "AI business knowledge",
        detail:
          "Make your configured facilities, hours and membership FAQs available to prospective members. Staff confirm current pricing and eligibility.",
      },
      {
        label: "Tour enquiries",
        question: "Can I come and look around the gym?",
        answer:
          "The team would be happy to help arrange a visit. Share your preferred day here, and they can confirm the details with you.",
        steps: ["Guide the visit enquiry", "Organize the interested contact", "Assign a team member to follow up"],
        feature: "Chat menus, CRM & assignments",
        detail:
          "Keep tour enquiries organized with contact details, tags and lead stages. Give the membership team the conversation context to follow up.",
      },
      {
        label: "Member updates",
        question: "Where can I hear about upcoming gym events?",
        answer:
          "The membership team can explain your options for receiving gym updates. Would you like me to connect you with them?",
        steps: ["Group opted-in members", "Prepare an event template", "Schedule member communication"],
        feature: "Phonebooks & campaigns",
        detail:
          "Organize eligible members and share event information or membership announcements through scheduled template campaigns.",
      },
    ],
    setup: [
      "Add facility details and approved membership FAQs.",
      "Create enquiry menus and contact tags.",
      "Connect your membership team and campaign audiences.",
    ],
    boundary:
      "Your team confirms tours, membership terms and prices. These examples do not imply class booking, access-control integration or personalized fitness advice.",
    faq: [
      "Does this manage class attendance or gym access?",
      "Creative Connects supports messaging, contact organization and follow-up workflows. A built-in class scheduler, attendance system or gym access integration is not implied.",
    ],
  },
  {
    slug: "ecommerce",
    name: "Ecommerce",
    audience: "Store owners & customer support teams",
    headline: "Help shoppers find their next favourite.",
    description:
      "Bring AI product answers, guided shopping menus and customer support into the same workspace as your catalog and orders.",
    summary: "Product discovery, guided shopping and order support.",
    contact: "Shopper enquiry",
    team: "Store support team",
    scenarios: [
      {
        label: "Product discovery",
        question: "Can you help me find a gift?",
        answer:
          "Of course. What kind of gift are you looking for? You can browse the catalog, or the store team can help you explore the options.",
        steps: ["Understand the shopper's question", "Guide them to the catalog", "Offer personal assistance"],
        feature: "AI assistant & product catalog",
        detail:
          "Combine business knowledge with a configured catalog and shopping menus so shoppers have a clear route to discovering your products.",
      },
      {
        label: "Guided shopping",
        question: "I would like to buy an item from the catalog.",
        answer:
          "Start by choosing your item in the shopping menu. The configured ordering flow will guide the next steps, and the store team can help if needed.",
        steps: ["Open a shopping menu", "Choose a product", "Manage the resulting order"],
        feature: "Chat menus & commerce",
        detail:
          "Configure product selection and ordering flows alongside your catalog, inventory, payments and order management.",
      },
      {
        label: "Order support",
        question: "I have a question about my order.",
        answer:
          "The store team can help. Please share your order reference in this conversation so they can review the details and get back to you.",
        steps: ["Identify a support enquiry", "Keep the conversation history", "Let support review the order"],
        feature: "Shared inbox & orders",
        detail:
          "Keep customer questions and order operations in one workspace. Assign a team member to review the order and continue the conversation.",
      },
    ],
    setup: [
      "Add store FAQs and product information.",
      "Configure your catalog and shopping menus.",
      "Connect support staff and commerce settings.",
    ],
    boundary:
      "Catalog, inventory, delivery and payments depend on your configuration. No automatic marketplace synchronization or abandoned-cart recovery is implied.",
    faq: [
      "Will it automatically sync with my existing online store?",
      "The application includes catalog and commerce features. A connection to a particular ecommerce provider must be confirmed and configured separately; these examples do not promise a universal store integration.",
    ],
  },
];

export const industries: Industry[] = [
  ...existingIndustries,
  {
    slug: "events-webinars",
    name: "Events & Webinar",
    audience: "Event organizers, hosts & audience teams",
    headline: "Keep your audience connected before the event begins.",
    description:
      "Use AI to answer event questions, guide registration enquiries and organize timely updates for your opted-in audience.",
    summary: "Event FAQs, registration enquiries and audience updates.",
    contact: "Attendee enquiry",
    team: "Event team",
    scenarios: [
      {
        label: "Event questions",
        question: "What will the webinar cover?",
        answer:
          "I can share the agenda and speaker information provided by the event team. Would you like the session topics, event timings or help from an organizer?",
        steps: ["Recognize an event question", "Answer from event information", "Offer organizer assistance"],
        feature: "AI assistant & business knowledge",
        detail:
          "Add the agenda, venue or joining guidance to your assistant so attendees can explore the event before contacting an organizer.",
      },
      {
        label: "Registration help",
        question: "How can I register for the next session?",
        answer:
          "The event team can share the registration instructions. Are you looking for the registration link, session details or help with an existing enquiry?",
        steps: ["Open a registration help menu", "Identify the attendee's question", "Continue with the event team"],
        feature: "Chat menus & shared inbox",
        detail:
          "Give registration questions a consistent starting point. Let organizers confirm registration, ticket details and joining arrangements using your existing event tools.",
      },
      {
        label: "Audience updates",
        question: "How can I receive event updates?",
        answer:
          "The event team can explain the available updates and help you choose whether to receive them. Would you like to speak with an organizer?",
        steps: ["Group opted-in attendees", "Prepare an event message template", "Schedule an audience campaign"],
        feature: "Phonebooks & scheduled campaigns",
        detail:
          "Organize eligible attendees into phonebooks and schedule approved template messages for event information or follow-up resources.",
      },
    ],
    setup: [
      "Add your agenda, event FAQs and joining guidance.",
      "Build menus for registration and attendee support.",
      "Organize opted-in audiences and message templates.",
    ],
    boundary:
      "Your team supplies event details and confirms registration. Ticketing, webinar hosting and attendance remain in your existing event services unless a separate integration is configured.",
    faq: [
      "Does this host webinars or issue tickets?",
      "Creative Connects helps with event communication and registration enquiries. Your existing webinar or ticketing service handles sessions, tickets and attendance.",
    ],
  },
  {
    slug: "it-services-internet",
    name: "IT Services & Internet",
    audience: "IT service providers, ISPs & support teams",
    headline: "Give every support enquiry a clearer starting point.",
    description:
      "Answer service FAQs with AI, guide connection enquiries and give your support team the conversation context to continue.",
    summary: "Service information, connection enquiries and support routing.",
    contact: "Service enquiry",
    team: "Support team",
    scenarios: [
      {
        label: "Service questions",
        question: "What services do you offer for small businesses?",
        answer:
          "I can share the service information provided by the team. Are you interested in IT support, internet services or speaking with a service specialist?",
        steps: [
          "Identify the service interest",
          "Answer from approved service FAQs",
          "Offer a specialist conversation",
        ],
        feature: "AI assistant & chat menus",
        detail:
          "Make your approved service descriptions, hours and contact options available to prospective customers without repeating the same information in every conversation.",
      },
      {
        label: "Connection enquiries",
        question: "Can I ask about internet service at my location?",
        answer:
          "The service team can review your enquiry. Please continue with them to confirm coverage, installation options and current availability.",
        steps: ["Recognize a connection enquiry", "Organize the interested contact", "Assign the service team"],
        feature: "Contact tags & team assignments",
        detail:
          "Keep coverage and installation enquiries organized for your team to review. Staff confirm availability through your existing service systems.",
      },
      {
        label: "Support routing",
        question: "I need help with my internet connection.",
        answer:
          "I can help you reach the right support team. Is this a connection issue, a service information question or another support enquiry?",
        steps: ["Open a support topic menu", "Keep the conversation history", "Let a support agent continue"],
        feature: "Shared inbox & human handoff",
        detail:
          "Use menus to identify the enquiry topic and give agents the existing message context. Share only troubleshooting guidance approved by your service team.",
      },
    ],
    setup: [
      "Add approved service FAQs and support guidance.",
      "Create menus for sales, connections and support.",
      "Connect your specialists and organize contact tags.",
    ],
    boundary:
      "Staff confirm coverage and service availability. Network diagnostics, provisioning, billing and ticket systems require your existing tools or separately configured integrations.",
    faq: [
      "Can AI diagnose or restore a network connection?",
      "It can share approved information and guide the enquiry to your support team. Network diagnostics and service restoration remain with your operational tools and staff.",
    ],
  },
  {
    slug: "hr-recruitment",
    name: "HR & Recruitment",
    audience: "Recruiters, hiring teams & people operations",
    headline: "Keep candidates informed while your team keeps hiring.",
    description:
      "Use AI for hiring FAQs, guide candidate enquiries and keep recruiter conversations organized from the first message.",
    summary: "Role FAQs, application enquiries and recruiter follow-up.",
    contact: "Candidate enquiry",
    team: "Recruitment team",
    scenarios: [
      {
        label: "Role questions",
        question: "Where can I learn more about the open role?",
        answer:
          "I can share the role information provided by the recruitment team. Would you like to know about responsibilities, the application process or contacting a recruiter?",
        steps: ["Recognize the role enquiry", "Answer from approved role information", "Offer recruiter assistance"],
        feature: "AI business knowledge",
        detail:
          "Teach the assistant approved role descriptions, application instructions and common hiring questions. Recruiters confirm current openings and individual details.",
      },
      {
        label: "Application help",
        question: "How do I send an application?",
        answer:
          "The recruitment team can share the approved application instructions. Would you like the application information or help from a recruiter?",
        steps: ["Guide the application enquiry", "Organize the contact and interest", "Continue with a recruiter"],
        feature: "Chat menus, contacts & shared inbox",
        detail:
          "Provide a clear starting point for application questions, organize interested contacts and let recruiters continue individual enquiries through the shared inbox.",
      },
      {
        label: "Recruiter follow-up",
        question: "Can I speak to someone about the next step?",
        answer:
          "Of course. A recruiter can continue this conversation and explain the next step for your enquiry. They will confirm any interview arrangements personally.",
        steps: ["Recognize a follow-up request", "Keep the candidate's message context", "Assign the recruitment team"],
        feature: "Team assignments & human handoff",
        detail:
          "Keep candidate messages in one place and assign follow-up to your team. Recruiters confirm interview times and hiring decisions using their existing process.",
      },
    ],
    setup: [
      "Add approved role information and hiring FAQs.",
      "Create menus and tags for candidate enquiries.",
      "Connect recruiters to follow up personally.",
    ],
    boundary:
      "Your team controls hiring decisions and candidate communication. Applicant tracking, background checks, payroll and interview calendars remain in your existing HR systems.",
    faq: [
      "Does AI screen candidates or make hiring decisions?",
      "These examples cover information and communication. Recruiters review applications and make hiring decisions; automated candidate scoring or an applicant-tracking integration is not implied.",
    ],
  },
  {
    slug: "banking-fintech",
    name: "Banking & Fintech",
    audience: "Customer service, onboarding & support teams",
    headline: "Guide service questions to the right team.",
    description:
      "Answer approved service FAQs with AI, guide application enquiries and connect customers with your support team for individual help.",
    summary: "Approved FAQs, application guidance and customer support.",
    contact: "Customer enquiry",
    team: "Customer support team",
    scenarios: [
      {
        label: "Service information",
        question: "Where can I learn about your services?",
        answer:
          "I can share the general service information approved by the team. Are you looking for product information, application guidance or customer support?",
        steps: [
          "Identify the service question",
          "Answer from approved public FAQs",
          "Offer customer service assistance",
        ],
        feature: "AI business knowledge & menus",
        detail:
          "Give customers a clear path to approved public information, opening hours and contact options. Keep account-specific requests with authorized staff.",
      },
      {
        label: "Application guidance",
        question: "How can I ask about the application process?",
        answer:
          "The team can explain the approved application steps. Would you like general process information or a conversation with customer support?",
        steps: [
          "Open an application information menu",
          "Share approved general guidance",
          "Refer individual questions to staff",
        ],
        feature: "Chat menus & human handoff",
        detail:
          "Use menus and approved information to explain where customers can begin. Staff confirm eligibility, documentation and application progress through your existing secure systems.",
      },
      {
        label: "Customer support",
        question: "I need help from the customer service team.",
        answer:
          "I can help you reach the team. Please do not share passwords, PINs or one-time codes here. Authorized staff can guide you to the appropriate support channel.",
        steps: [
          "Recognize a request for support",
          "Identify the general enquiry topic",
          "Assign the customer support team",
        ],
        feature: "Shared inbox & team assignments",
        detail:
          "Organize general support enquiries and keep conversation context available to authorized staff. Direct sensitive account requests to your established secure channels.",
      },
    ],
    setup: [
      "Add approved public service FAQs.",
      "Define menus and boundaries for general enquiries.",
      "Assign authorized staff and secure support routes.",
    ],
    boundary:
      "Examples cover general communication, not financial advice or account transactions. Core banking, identity checks and payment execution require approved systems and integrations; no financial-services compliance certification is implied.",
    faq: [
      "Can the assistant access accounts or move money?",
      "These examples do not access account balances or execute transactions. Authorized staff use your established secure systems for account-specific requests and regulated operations.",
    ],
  },
];

export const industryAliases: Record<string, string> = {
  schools: "education-edtech",
  clinics: "healthcare",
  salons: "spas-salons",
};

export function findIndustry(slug: string | undefined) {
  const canonical = slug ? (industryAliases[slug] ?? slug) : undefined;
  return industries.find((industry) => industry.slug === canonical);
}
