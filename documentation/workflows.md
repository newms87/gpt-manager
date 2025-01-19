# Workflows Overview

## System Jobs

* These are generalized jobs that can be run independently by the system
* They are defined by an expected input artifact and an expected output artifact
* Agents can run jobs as part of their response
    * An agent can choose to receive the response from the job and run the thread again to provide an output based on
      the response of the job
    * An agent can choose to run a job as their final output
* Jobs can be run as part of a workflow, they can be assigned organization by Workflow Jobs

## Agents

* An agent is an LLM API defined by a user
* Agents are assigned a combination of Directives and a Prompt Schema to define the input and output of the agent
* Agent Jobs
    * Agents can optionally choose to respond with a jobs list instead of the normal JSON Schema (or text) response
    * While Open AI uses the idea of function calling to allow running specific functions from the server, this is
      easily done by returning a jobs list. Returning a jobs list will in turn append response messages for the jobs
    *

## Artifacts

* An artifact is a record containing a useful output of a Workflow Task
    * `JSON`  - A JSON object: can be arbitrary or limited to a defined structure using a Prompt Schema + sub
      selection (ie: JSON Schema)
    * `Text` - A text string
    * `Files` - A list of files stored in the DB (see StoredFiles model)

## Workflow

* A workflow is a sequence of jobs that processes data.

### Workflow Job

* A workflow job is an organization layer in a workflow.
* Jobs are used to define dependencies and define groupings of input artifacts
* Jobs collect the output artifacts of the tasks as well to be passed on to the next job or used as the final output of
  a workflow

#### Tasks

* A task is a unit of work that is performed by the system
* A task can be run by an agent, as part of a job in a workflow, or a command of the system
* Tasks can be defined as one of the following types:
    * `Run Agents` - Run 1 or more agents
        * An agent job allows associating 1 or more agents as assignments
        * Jobs
    * `Function` - A function is a program that runs on the server
        * `Prepare Workflow Input` - A function that converts the Workflow Input into an artifact that can be
          consumed
          by the agents in a thread
    * `Webhook` - A webhook is a URL that is called by an external service
    * `Workflow` - Can run sub workflows as a job giving a lot more flexibility in how workflows are constructed

## Workflow Inputs

### Types

* Team Objects
* Files
* Content (text)

#### Team Objects

* Team objects are objects stored in the database
* Agents in a workflow can both read and write team objects

#### Files

* Files are stored in the file system
* Files can be transcoded to either images or texts for easier input for an agent in the workflow
* Agents in a workflow can both read and write files

##### Transcodes

* Agents in a workflow have an option to use their output as a transcode their output as
