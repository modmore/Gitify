---
layout: page
title: "Troubleshooting"
category: trouble
---

#Troubleshooting

Having issues with Gitify? Please read all entries in this guide and search the [issues on Github](https://github.com/modmore/Gitify/issues). If you don't find an answer you might want to [open a new issue](https://github.com/modmore/Gitify/issues/new).

## MIGX

If you're using MIGX configs and the Gitify files for the configs changes after each `build` and `extract` command of Gitify, you might need to pay attention to your order of the MIGX objects in your `.gitify` file. The `migxConfig` should be the last item of the MIGX objects. For more details please read [issue #90](https://github.com/modmore/Gitify/issues/90).