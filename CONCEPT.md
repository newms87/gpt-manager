# GPT Agent Manager

## Concept

### Generalized GPT Agent Manager

* Services will leverage the GPT Agent Manager to quickly spin up customized agents that are empowered with a growing
  list of abilities (ie: Ask PDF, Summarize URL, Search Web, etc.)
* Services don't need to manage threads / convos w/ GPT agents directly, they can focus on higher level tasks while GPT
  Agent manager provides the best results for a services prompts / files

## Domain Model

https://miro.com/app/board/uXjVKU0jWSA=/

### Agents

* id
* name varchar(255)
* prompt text
* knowledge JSON

### Threads

* id
* agent_id
* name

### Messages

* id
* thread_id
* role
* content
* timestamp

### Files

* id
* url
* parsed_text TEXT
* images JSON

## Functions

Functions are the meat of GPT Manager.
The value comes from a generalized set of utilities that enables other services
using GPT manager to accomplish their tasks.

### Search Web

Returns a list of web results with a preview of the contents

#### Input

* url

#### Return

* an array of search results w/ summaries

### Get URL

Returns all the parsed content from a URL.
Maybe this should also return an image of the URL in the case the parsed text cannot be understood by the requesting GPT
Agent.

#### Input

* url
* format: 'text' or 'image'

#### Return

* An image or the text content of the URL

### Summarize URL

Asks a GPT agent in a separate thread to read a URL and summarize the content / return information based on the prompt

#### Input

* prompt
* url

#### Return

* The summary of the URL

### Ask PDF

1. Extract the text of the PDF and convert the PDF to images.
    * NOTE: The PDF is a File record. The parsed text / images should be associated to this file record for later use
      when asking multiple questions about the same PDF.
2. Ask the given GPT Agent to answer the prompt given the text
3. Ask the given GPT Agent to answer the prompt given the images
4. Ask the given GPT Agent what is the best answer to the prompt of the 2 provided answers
5. Return the best answer

#### Input

* agent_id
* file_id
* prompt

#### Return

* The answer to the prompt

## API

### Create Agent

* name
* knowledge
    * files[]
* prompt

### Update Agent

* name
* knowledge
    * files[]
* prompt

### Delete Agent (Soft Deletes)

* agent_id

### Start Thread

* agent_id
* name

### Send Message

* thread_id
* text
* files[]

### Get Presigned Upload URL

TODO: Check if Laravel already has something for this

### Presigned Upload URL Complete

* file_id
