import type { Meta, StoryObj } from "@storybook/react";
import { Editor } from "../Editor";
import { Preview } from "../Preview";

const sample = `@startuml\nAlice -> Bob: Hello\n@enduml`;

const meta: Meta = {
  title: "Studio/Editor+Preview",
};

export default meta;

type Story = StoryObj;

export const Split: Story = {
  render: () => (
    <div className="grid h-screen grid-cols-2 bg-paper p-5">
      <Editor code={sample} onChange={() => {}} />
      <Preview svg="<svg xmlns='http://www.w3.org/2000/svg' width='400' height='120'><rect width='400' height='120' fill='#fff'/><text x='20' y='60'>Sample SVG</text></svg>" />
    </div>
  ),
};
