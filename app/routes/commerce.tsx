import { CommerceWorkspace } from "~/components/commerce/commerce-workspace";
export function meta() {
  return [{ title: "Commerce setup — Creative Connects" }];
}
export default function Commerce() {
  return <CommerceWorkspace section="setup" />;
}
