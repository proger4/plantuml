import type { Meta, StoryObj } from "@storybook/react";
import { Button } from "../uikit/Button";
import { Input } from "../uikit/Input";
import { Panel } from "../uikit/Panel";
import { Badge } from "../uikit/Badge";

const meta: Meta = {
  title: "UIKit/Kit",
};

export default meta;

type Story = StoryObj;

export const Overview: Story = {
  render: () => (
    <div className="grid min-h-screen place-items-center bg-paper p-10">
      <Panel className="w-full max-w-xl space-y-4 p-6">
        <div className="flex gap-2">
          <Badge>neutral</Badge>
          <Badge tone="ok">ok</Badge>
          <Badge tone="warn">warn</Badge>
        </div>
        <Input placeholder="Login" />
        <div className="flex gap-2">
          <Button>Primary</Button>
          <Button variant="ghost">Ghost</Button>
        </div>
      </Panel>
    </div>
  ),
};
