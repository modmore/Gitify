---
layout: default
lang: en
---

# Gitify

The goal of Gitify is to provide a **two-way sync** of data typically stored in the MODX database, making it versionable with Git. To do this, it creates a representation of MODX objects in files. These files follow a certain [human and machine friendly format](https://gist.github.com/Mark-H/5acafdc1c364f70fa4e7), built from a block of YAML, followed by a separator, and then the main content (if there's a specific content field) below that.

The project configuration, which determines what data is written to file and build to the database, is stored in a `.gitify` file in the project root.

## Introductions to Gitify

- [Video of Mark introduction Gitify](https://video.modmore.com/modx-weekend-2014/sunday-backend/staging-workflow-with-git-and-gitify/) at the MODX Weekend 2014, 2015-09-21. **Important:** This presentation talks about a very early version of Gitify. It has learned a lot of new tricks since!

- [Mark's slides of the MODX Meetup](http://www.slideshare.net/hamstramark1/solving-the-workflow-building-modxtoday-with-gitify-20150521-alkmaar) in Alkmaar, The Netherlands, where he talked about how Gitify is used to build and manage Gitify. This is based on Gitify 0.9, 2015-05-21, so a lot more up to date on what you can do with it, and how you can manage a workflow with Gitify. Unfortunately, no video footage of this one. 

- ["Building MODX.today"](https://modx.today/posts/2015/04/building-modx.today), an article published on 2015-04-22 about how the MODX.today site was built. Includes a short section about using Gitify.



